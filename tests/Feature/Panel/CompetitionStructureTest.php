<?php

use App\Models\User;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\Gender;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Models\Game;
use App\Modules\Rules\RuleSet;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    // Dados criados pelas fábricas ficam na organização padrão, a mesma que o painel usa nas requisições.
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));

    $this->actingAs(User::factory()->create());
});

function seasonPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => '2026',
        'year' => 2026,
        'starts_on' => '2026-03-01',
        'ends_on' => '2026-06-30',
        'status' => 'active',
        'rules' => RuleSet::defaults()->toArray(),
    ], $overrides);
}

it('creates a competition with a unique slug and opens it', function () {
    $this->post('/painel/competitions', ['name' => 'Futebol Amador', 'description' => 'Liga municipal'])->assertRedirect();
    $this->post('/painel/competitions', ['name' => 'Futebol Amador']);

    $slugs = Competition::query()->orderBy('created_at')->pluck('slug')->all();
    expect($slugs)->toBe(['futebol-amador', 'futebol-amador-2']);

    $competition = Competition::query()->firstOrFail();

    $this->get("/painel/competitions/{$competition->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Panel/Competitions/Show')->where('competition.name', 'Futebol Amador')->has('seasons', 0));
});

it('requires a name for a competition and updates it', function () {
    $this->post('/painel/competitions', ['name' => ''])->assertSessionHasErrors('name');

    $competition = Competition::factory()->create();
    $this->put("/painel/competitions/{$competition->id}", ['name' => 'Novo nome'])->assertRedirect();

    expect($competition->fresh()->name)->toBe('Novo nome');
});

it('creates a season storing the normalized regulation', function () {
    $competition = Competition::factory()->create();

    $payload = seasonPayload(['rules' => [
        'points' => ['win' => 2],
        'tiebreakers' => ['goal_difference', 'head_to_head', 'draw'],
        'cards' => ['yellow_for_suspension' => 2, 'reset_yellows_between_stages' => false],
        'walkover' => ['winner_goals' => 1],
    ]]);
    $payload['rules']['tiebreakers'] = ['goal_difference', 'head_to_head', 'draw'];

    $this->post("/painel/competitions/{$competition->id}/seasons", $payload)->assertRedirect();

    $season = Season::query()->firstOrFail();
    $rules = $season->ruleSet();

    expect($season->status)->toBe(SeasonStatus::Active)
        ->and($season->competition_id)->toBe($competition->id)
        ->and($rules->pointsWin)->toBe(2)
        ->and($rules->yellowCardsForSuspension)->toBe(2)
        ->and($rules->resetYellowsBetweenStages)->toBeFalse()
        ->and($rules->walkoverWinnerGoals)->toBe(1)
        ->and(array_map(fn ($t) => $t->value, $rules->tiebreakers))->toBe(['goal_difference', 'head_to_head', 'draw']);
});

it('validates the season fields and regulation', function () {
    $competition = Competition::factory()->create();

    $this->post("/painel/competitions/{$competition->id}/seasons", seasonPayload(['ends_on' => '2026-01-01']))
        ->assertSessionHasErrors('ends_on');

    $bad = seasonPayload();
    $bad['rules']['tiebreakers'] = ['wins', 'wins'];
    $bad['rules']['cards']['yellow_for_suspension'] = 0;
    $this->post("/painel/competitions/{$competition->id}/seasons", $bad)
        ->assertSessionHasErrors(['rules.tiebreakers.0', 'rules.cards.yellow_for_suspension']);

    $invalid = seasonPayload();
    $invalid['rules']['tiebreakers'] = ['coin_flip'];
    $this->post("/painel/competitions/{$competition->id}/seasons", $invalid)->assertSessionHasErrors('rules.tiebreakers.0');

    expect(Season::query()->count())->toBe(0);
});

it('rejects a duplicated season name within the same competition only', function () {
    $competition = Competition::factory()->create();
    $this->post("/painel/competitions/{$competition->id}/seasons", seasonPayload())->assertSessionHasNoErrors();
    $this->post("/painel/competitions/{$competition->id}/seasons", seasonPayload())->assertSessionHasErrors('name');

    $other = Competition::factory()->create();
    $this->post("/painel/competitions/{$other->id}/seasons", seasonPayload())->assertSessionHasNoErrors();
});

it('shows the season with its divisions and effective regulation', function () {
    $season = Season::factory()->create(['rules' => ['points' => ['win' => 2]]]);
    Division::factory()->for($season)->create(['name' => 'SUB-15', 'gender' => Gender::Male]);

    $this->get("/painel/seasons/{$season->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Seasons/Show')
            ->where('rules.points.win', 2)
            ->where('rules.points.draw', 1)
            ->has('divisions', 1)
            ->where('divisions.0.gender_label', 'Masculino'));
});

it('updates and deletes a season', function () {
    $season = Season::factory()->create();

    $this->put("/painel/seasons/{$season->id}", seasonPayload(['name' => 'Edição 2027', 'year' => 2027]))->assertRedirect();
    expect($season->fresh()->name)->toBe('Edição 2027');

    $this->delete("/painel/seasons/{$season->id}")->assertRedirect(route('panel.competitions.show', $season->competition_id));
    expect(Season::query()->count())->toBe(0);
});

it('creates divisions and keeps their names unique per season', function () {
    $season = Season::factory()->create();

    $this->post("/painel/seasons/{$season->id}/divisions", ['name' => 'SUB-15', 'category' => 'Base', 'gender' => 'male'])->assertRedirect();
    $this->post("/painel/seasons/{$season->id}/divisions", ['name' => 'SUB-15', 'gender' => 'male'])->assertSessionHasErrors('name');
    $this->post("/painel/seasons/{$season->id}/divisions", ['name' => 'SUB-17', 'gender' => 'robot'])->assertSessionHasErrors('gender');

    expect(Division::query()->count())->toBe(1);
});

it('manages stages inside a division and blocks deleting one with games', function () {
    $division = Division::factory()->create();

    $this->post("/painel/divisions/{$division->id}/stages", ['name' => 'Fase de grupos', 'type' => 'groups', 'sequence' => 1])->assertRedirect();
    $this->post("/painel/divisions/{$division->id}/stages", ['name' => 'Outra', 'type' => 'league', 'sequence' => 1])->assertSessionHasErrors('sequence');

    $stage = Stage::query()->firstOrFail();
    expect($stage->type)->toBe(StageType::Groups);

    $this->put("/painel/stages/{$stage->id}", ['name' => 'Grupos', 'type' => 'league', 'sequence' => 1])->assertRedirect();
    expect($stage->fresh()->type)->toBe(StageType::League);

    Game::factory()->for($stage)->create();
    $this->delete("/painel/stages/{$stage->id}")->assertSessionHas('error');
    expect(Stage::query()->count())->toBe(1);

    $empty = Stage::factory()->for($division)->create();
    $this->delete("/painel/stages/{$empty->id}")->assertSessionHas('success');
});

it('shows a division with its stages, entries and only the teams not yet entered', function () {
    $division = Division::factory()->create();
    $entered = Entry::factory()->for($division)->create(['seed' => 1]);
    $free = Team::factory()->create(['organization_id' => $division->organization_id, 'name' => 'Livre FC']);

    $this->get("/painel/divisions/{$division->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Panel/Divisions/Show')
            ->has('entries', 1)
            ->where('entries.0.team.id', $entered->team_id)
            ->has('available_teams', 1)
            ->where('available_teams.0.id', $free->id));
});

it('enters teams in a division once and updates or removes the entry', function () {
    $division = Division::factory()->create();
    $team = Team::factory()->create(['organization_id' => $division->organization_id]);

    $this->post("/painel/divisions/{$division->id}/entries", ['team_id' => $team->id, 'seed' => 3, 'status' => 'confirmed'])->assertRedirect();
    $this->post("/painel/divisions/{$division->id}/entries", ['team_id' => $team->id, 'status' => 'confirmed'])
        ->assertSessionHasErrors(['team_id' => 'Esta equipe já está inscrita nesta divisão.']);
    $this->post("/painel/divisions/{$division->id}/entries", ['team_id' => 'inexistente', 'status' => 'confirmed'])->assertSessionHasErrors('team_id');

    $entry = Entry::query()->firstOrFail();
    expect($entry->seed)->toBe(3)->and($entry->status)->toBe(EntryStatus::Confirmed);

    $this->put("/painel/entries/{$entry->id}", ['seed' => 1, 'status' => 'withdrawn', 'team_id' => 'ignorado'])->assertRedirect();
    expect($entry->fresh()->seed)->toBe(1)
        ->and($entry->fresh()->status)->toBe(EntryStatus::Withdrawn)
        ->and($entry->fresh()->team_id)->toBe($team->id);

    $this->delete("/painel/entries/{$entry->id}")->assertSessionHas('success');
    expect(Entry::query()->count())->toBe(0);
});

it('does not enter a team of another organization', function () {
    $division = Division::factory()->create();
    $foreign = Team::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->post("/painel/divisions/{$division->id}/entries", ['team_id' => $foreign->id, 'status' => 'confirmed'])
        ->assertSessionHasErrors('team_id');
});

it('blocks removing an entry that already has games', function () {
    $game = Game::factory()->create();

    $this->delete("/painel/entries/{$game->home_entry_id}")->assertSessionHas('error');

    expect(Entry::query()->whereKey($game->home_entry_id)->exists())->toBeTrue();
});
