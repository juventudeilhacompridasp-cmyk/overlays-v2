<?php

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['sheet_import']]));
    Storage::fake('s3-private');
});

/** Jogo 1 x 0 com Fulano (10) marcando; o sistema não tem a escalação do visitante. */
function reviewGame(): Game
{
    $game = Game::factory()->create();
    Game::query()->whereKey($game->getKey())->update(['home_score' => 1, 'away_score' => 0]);
    $fulano = Person::factory()->create(['name' => 'Fulano de Tal']);
    GameLineup::factory()->create(['game_id' => $game->getKey(), 'entry_id' => $game->home_entry_id, 'person_id' => $fulano->getKey(), 'shirt_number' => 10]);
    GameEvent::factory()->create(['game_id' => $game->getKey(), 'type' => GameEventType::Goal, 'entry_id' => $game->home_entry_id, 'person_id' => $fulano->getKey()]);

    return $game->fresh(['homeEntry.team', 'awayEntry.team']);
}

function readSheet(Game $game, SheetUploadStatus $status = SheetUploadStatus::Read, array $extracted = []): GameSheetUpload
{
    return GameSheetUpload::query()->create([
        'game_id' => $game->getKey(),
        'path' => 'x/'.uniqid().'.pdf',
        'original_name' => 'sumula.pdf',
        'mime' => 'application/pdf',
        'kind' => 'pdf',
        'size' => 10,
        'sha256' => hash('sha256', uniqid()),
        'status' => $status,
        'extracted' => array_merge([
            'home_team' => $game->homeEntry->team->name,
            'away_team' => $game->awayEntry->team->name,
            'home_score' => 1,
            'away_score' => 0,
            'lineups' => [
                ['team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'starter' => true, 'confidence' => 0.9],
                ['team' => 'home', 'name' => 'Beltrano', 'shirt' => 7, 'starter' => false, 'confidence' => 0.4],
            ],
            'events' => [
                ['type' => 'goal', 'team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'minute' => '23', 'detail' => null, 'confidence' => 0.9],
                ['type' => 'yellow_card', 'team' => 'home', 'name' => 'Beltrano', 'shirt' => 7, 'minute' => '40', 'detail' => null, 'confidence' => 0.8],
            ],
            'notes' => null,
            'confidence' => 0.8,
        ], $extracted),
    ]);
}

function confirmReview(GameSheetUpload $upload, array $data, Role $role = Role::Operator)
{
    return test()->actingAs(panelUser($role))->post(route('panel.games.uploads.review.confirm', $upload), $data);
}

describe('review', function () {
    it('shows the reading to any member, but only for sheets that were read', function () {
        $game = reviewGame();
        $read = readSheet($game);
        $unread = GameSheetUpload::query()->create(['game_id' => $game->getKey(), 'path' => 'x/y.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'kind' => 'pdf', 'size' => 1, 'sha256' => hash('sha256', 'a'), 'status' => SheetUploadStatus::Uploaded]);

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.review', $read))->assertInertia(fn ($page) => $page
            ->component('Panel/Games/SheetReview')
            ->where('sheet.home_score', 1)
            ->has('sheet.lineups', 2)
            ->where('reviewed', false));

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.review', $unread))->assertRedirect(route('panel.games.uploads', $game));
    });

    it('stores the corrected reading and what changed, keeping the original and leaving the game alone', function () {
        $game = reviewGame();
        $upload = readSheet($game);
        $eventsBefore = GameEvent::query()->count();

        confirmReview($upload, [
            'home_team' => $game->homeEntry->team->name,
            'away_team' => $game->awayEntry->team->name,
            'home_score' => 2,
            'away_score' => 0,
            'lineups' => [
                ['team' => 'home', 'name' => 'Fulano de Tal', 'shirt' => 10, 'starter' => true],
            ],
            'events' => [
                ['type' => 'goal', 'team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'minute' => '23'],
            ],
            'notes' => 'RG 12.345.678-9 do árbitro',
        ])->assertRedirect(route('panel.games.uploads.report', $upload));

        $upload->refresh();

        expect($upload->status)->toBe(SheetUploadStatus::Reviewed)
            ->and($upload->reviewed['home_score'])->toBe(2)
            ->and($upload->reviewed['notes'])->toBe('RG [removido] do árbitro')
            ->and($upload->extracted['home_score'])->toBe(1)
            ->and($upload->extracted['lineups'])->toHaveCount(2)
            ->and($upload->review_changes)->toContain('Placar da casa: 1 → 2')
            ->and($upload->review_changes)->toContain('Escalação: 1 linha(s) removida(s)')
            ->and($upload->review_changes)->toContain('Lances: 1 linha(s) removida(s)')
            ->and($upload->reviewed_by)->not->toBeNull()
            ->and($upload->reviewed_at)->not->toBeNull();

        $game->refresh();
        expect([$game->home_score, $game->away_score])->toBe([1, 0])
            ->and(GameEvent::query()->count())->toBe($eventsBefore);
    });

    it('lets only those who operate games confirm, and validates the data', function () {
        $upload = readSheet(reviewGame());

        confirmReview($upload, ['home_score' => 1], Role::Viewer);
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Read);

        confirmReview($upload, ['home_score' => 5000, 'events' => [['type' => 'inventado', 'team' => 'home']]])->assertSessionHasErrors(['home_score', 'events.0.type']);
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Read);
    });

    it('can be reviewed again after the first confirmation', function () {
        $game = reviewGame();
        $upload = readSheet($game);

        confirmReview($upload, ['home_score' => 1, 'away_score' => 0]);
        confirmReview($upload, ['home_score' => 0, 'away_score' => 0])->assertRedirect();

        expect($upload->refresh()->reviewed['home_score'])->toBe(0);
    });
});

describe('conference report', function () {
    it('compares the reviewed sheet with the game and reports each point', function () {
        $game = reviewGame();
        $upload = readSheet($game);
        confirmReview($upload, [
            'home_team' => $game->homeEntry->team->name,
            'away_team' => $game->awayEntry->team->name,
            'home_score' => 1,
            'away_score' => 0,
            'lineups' => [
                ['team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'starter' => true],
                ['team' => 'home', 'name' => 'Beltrano', 'shirt' => 7, 'starter' => false],
            ],
            'events' => [
                ['type' => 'goal', 'team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'minute' => '23'],
                ['type' => 'yellow_card', 'team' => 'home', 'name' => 'Beltrano', 'shirt' => 7, 'minute' => '40'],
            ],
        ]);

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.report', $upload))->assertInertia(fn ($page) => $page
            ->component('Panel/Games/SheetReport')
            ->where('summary', ['match' => 6, 'differs' => 0, 'only_sheet' => 2, 'only_system' => 0, 'unclear' => 0])
            ->has('sections')
            ->where('changes', []));
    });

    it('ignores events that were voided in the game', function () {
        $game = reviewGame();
        $goal = GameEvent::query()->where('game_id', $game->getKey())->firstOrFail();
        GameEvent::factory()->create(['game_id' => $game->getKey(), 'type' => GameEventType::Goal, 'entry_id' => $game->home_entry_id, 'compensates_event_id' => $goal->getKey()]);
        $upload = readSheet($game, extracted: ['events' => []]);
        confirmReview($upload, ['home_score' => 1, 'away_score' => 0, 'lineups' => [['team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'starter' => true]], 'events' => []]);

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.report', $upload))->assertInertia(fn ($page) => $page->where('summary.only_system', 0)->where('summary.only_sheet', 0));
    });

    it('only exists after the review is confirmed', function () {
        $upload = readSheet(reviewGame());

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.report', $upload))->assertRedirect();
        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.report.csv', $upload))->assertNotFound();
    });

    it('downloads the report as CSV with the formula guard', function () {
        $game = reviewGame();
        $upload = readSheet($game);
        confirmReview($upload, [
            'home_score' => 1,
            'away_score' => 0,
            'lineups' => [['team' => 'home', 'name' => '=HYPERLINK("x")', 'shirt' => 99, 'starter' => true]],
            'events' => [],
        ]);

        $response = $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads.report.csv', $upload));

        $response->assertOk();
        $csv = $response->streamedContent();
        expect($csv)->toContain('Seção;Item;Situação')
            ->and($csv)->toContain("'=HYPERLINK")
            ->and($csv)->toContain('Só na súmula');
    });
});

describe('discarding', function () {
    it('discards a read sheet and then refuses to read or review it', function () {
        $upload = readSheet(reviewGame());

        $this->actingAs(panelUser(Role::Viewer))->post(route('panel.games.uploads.discard', $upload));
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Read);

        $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.uploads.discard', $upload))->assertSessionHas('success');
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Discarded);

        $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.uploads.read', $upload))->assertSessionHas('error');
        confirmReview($upload, ['home_score' => 1])->assertSessionHas('error');
    });
});

it('needs the sheet_import feature', function () {
    $upload = readSheet(reviewGame());
    CurrentOrganization::get()->update(['plan' => Plan::Pro->value, 'features' => null, 'opt_in_features' => null]);

    $this->actingAs(panelUser(Role::Admin))->get(route('panel.games.uploads.review', $upload))->assertForbidden();
    $this->actingAs(panelUser(Role::Admin))->get(route('panel.games.uploads.report', $upload))->assertForbidden();
});
