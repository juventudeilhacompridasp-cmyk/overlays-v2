<?php

use App\Modules\Matches\Actions\CloseGameSheet;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\ReopenGame;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameDocumentSignature;
use App\Modules\Matches\Models\GameSheetClosure;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\SheetClosingSettings;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function finishedGame(): Game
{
    ['stage' => $stage] = fixtureDivision(3);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    $game->update(['status' => GameStatus::Finished, 'finished_at' => now()->subHour(), 'home_score' => 1, 'away_score' => 0]);

    return $game->fresh();
}

function sign(Game $game, string $status = 'signed', ?DateTimeInterface $at = null): void
{
    GameDocumentSignature::query()->create([
        'organization_id' => $game->organization_id,
        'game_id' => $game->getKey(),
        'document_type' => 'sheet',
        'document_hash' => str_repeat('a', 64),
        'signer_name' => 'Fulano',
        'signer_role' => 'Árbitro',
        'method' => 'email_link',
        'status' => $status,
        'signed_at' => $status === 'signed' ? ($at ?? now()) : null,
    ]);
}

function closingPayload(array $override = []): array
{
    return $override + ['disabled' => [], 'mesario' => ['days_ahead' => 2, 'finished_hours' => 6, 'limit' => 40, 'history' => 15], 'portal' => ['home' => 8, 'widgets' => 6, 'scorers' => 10, 'stats_top' => 5, 'stats_form' => 5], 'public_api' => true, 'quick_result' => true];
}

function setClosing(array $values): void
{
    $organization = CurrentOrganization::get();
    $organization->update(['settings' => ($organization->settings ?? []) + ['sheet_closing' => $values]]);
}

it('keeps the original behavior by default: no signature required and a reason to reopen', function () {
    expect(SheetClosingSettings::all())->toBe(['min_signatures' => 0, 'reopen_reason' => true]);
});

it('closes the sheet of a finished game and records who and when', function () {
    $game = finishedGame();
    $operator = panelUser(Role::Operator);

    $this->actingAs($operator)->post(route('panel.games.sheet.close', $game), ['notes' => 'Tudo conferido'])->assertSessionHas('success');

    $closure = GameSheetClosure::query()->where('game_id', $game->id)->sole();
    expect($closure->closed_by_name)->toBe($operator->name)
        ->and($closure->notes)->toBe('Tudo conferido')
        ->and($game->fresh()->sheetClosure?->id)->toBe($closure->id);
});

it('refuses to close an open game or to close twice', function () {
    $game = finishedGame();
    $open = Game::query()->where('id', '!=', $game->id)->firstOrFail();

    $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.sheet.close', $open))->assertSessionHas('error');
    expect(GameSheetClosure::query()->count())->toBe(0);

    app(CloseGameSheet::class)->handle($game);
    $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.sheet.close', $game))->assertSessionHas('error');
    expect(GameSheetClosure::query()->count())->toBe(1);
});

it('requires the configured minimum of confirmed signatures made after the game finished', function () {
    $game = finishedGame();
    setClosing(['min_signatures' => 2, 'reopen_reason' => true]);

    sign($game);
    sign($game, 'pending');
    sign($game, 'signed', now()->subDays(2)); // antes do encerramento atual: não conta

    $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.sheet.close', $game))->assertSessionHas('error');
    expect(GameSheetClosure::query()->count())->toBe(0);

    sign($game);
    $this->actingAs(panelUser(Role::Operator))->post(route('panel.games.sheet.close', $game))->assertSessionHas('success');
    expect(GameSheetClosure::query()->sole()->signatures_count)->toBe(2);
});

it('asks for a reason to reopen a closed sheet and keeps the history', function () {
    $game = finishedGame();
    app(CloseGameSheet::class)->handle($game);

    expect(fn () => app(ReopenGame::class)->handle($game->fresh()))->toThrow(InvalidArgumentException::class, 'motivo');
    expect($game->fresh()->status)->toBe(GameStatus::Finished);

    $this->actingAs(panelUser(Role::Organizer))->post(route('panel.games.reopen', $game), ['reason' => 'Gol lançado no time errado'])->assertSessionHas('success');

    $closure = GameSheetClosure::query()->sole();
    expect($closure->reopen_reason)->toBe('Gol lançado no time errado')
        ->and($closure->reopened_at)->not->toBeNull()
        ->and($game->fresh()->sheetClosure)->toBeNull();

    // Fechada de novo, vira um segundo registro e o primeiro continua no histórico.
    $game->fresh()->update(['status' => GameStatus::Finished, 'finished_at' => now()]);
    app(CloseGameSheet::class)->handle($game->fresh());
    expect(GameSheetClosure::query()->count())->toBe(2);
});

it('lets the organization drop the reason requirement', function () {
    $game = finishedGame();
    setClosing(['min_signatures' => 0, 'reopen_reason' => false]);
    app(CloseGameSheet::class)->handle($game);

    $this->actingAs(panelUser(Role::Organizer))->post(route('panel.games.reopen', $game))->assertSessionHas('success');
    expect(GameSheetClosure::query()->sole()->reopen_reason)->toBeNull();
});

it('does not ask for a reason when the sheet was never closed', function () {
    $game = finishedGame();

    $this->actingAs(panelUser(Role::Organizer))->post(route('panel.games.reopen', $game))->assertSessionHas('success');
});

it('only lets who operates games close the sheet, while anyone can read it', function () {
    $game = finishedGame();

    $this->actingAs(panelUser(Role::Viewer))->post(route('panel.games.sheet.close', $game))->assertSessionMissing('success');
    expect(GameSheetClosure::query()->count())->toBe(0);

    $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.operator', $game))->assertOk()->assertInertia(fn ($page) => $page
        ->where('closure.closed', false)->where('closure.can_close', true)->where('closure.required', 0));

    app(CloseGameSheet::class)->handle($game);
    $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.operator', $game))->assertInertia(fn ($page) => $page->where('closure.closed', true)->where('closure.can_close', false));
});

it('saves the closing parameters and clamps the range', function () {
    $this->actingAs(panelUser(Role::Admin))->patch(route('panel.parameters.update'), closingPayload(['sheet_closing' => ['min_signatures' => 3, 'reopen_reason' => false]]))->assertSessionHas('success');
    expect(SheetClosingSettings::all())->toBe(['min_signatures' => 3, 'reopen_reason' => false]);

    $this->actingAs(panelUser(Role::Admin))->patch(route('panel.parameters.update'), closingPayload(['sheet_closing' => ['min_signatures' => 11, 'reopen_reason' => true]]))->assertSessionHasErrors('sheet_closing.min_signatures');
    expect(SheetClosingSettings::all()['min_signatures'])->toBe(3);

    // Sem o bloco no envio, o que estava salvo continua.
    $this->actingAs(panelUser(Role::Admin))->patch(route('panel.parameters.update'), closingPayload())->assertSessionHas('success');
    expect(SheetClosingSettings::all()['min_signatures'])->toBe(3);
});
