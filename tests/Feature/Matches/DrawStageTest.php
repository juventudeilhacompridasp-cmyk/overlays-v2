<?php

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Matches\Actions\DrawStage;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Scheduling\KnockoutBracket;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

/** @param  list<Entry>  $entries */
function entryIds(array $entries): array
{
    return array_map(fn (Entry $entry) => $entry->getKey(), $entries);
}

describe('KnockoutBracket::fromPairs', function () {
    it('builds the bracket exactly as informed, with byes going straight to round 2', function () {
        $ties = KnockoutBracket::fromPairs([['a', 'b'], ['c', null], ['d', 'e'], [null, 'f']]);

        $round1 = array_values(array_filter($ties, fn ($tie) => $tie->round === 1));
        $round2 = array_values(array_filter($ties, fn ($tie) => $tie->round === 2));

        expect($round1)->toHaveCount(4)
            ->and($round1[1]->bye)->toBeTrue()
            ->and($round1[3]->bye)->toBeTrue()
            ->and($round2)->toHaveCount(2)
            ->and([$round2[0]->a, $round2[0]->b])->toBe([null, 'c'])
            ->and([$round2[1]->a, $round2[1]->b])->toBe([null, 'f']);
    });

    it('refuses a number of ties that is not a power of two and an empty tie', function () {
        expect(fn () => KnockoutBracket::fromPairs([['a', 'b'], ['c', 'd'], ['e', 'f']]))->toThrow(InvalidArgumentException::class);
        expect(fn () => KnockoutBracket::fromPairs([['a', 'b'], [null, null]]))->toThrow(InvalidArgumentException::class, 'sem nenhuma equipe');
        expect(fn () => KnockoutBracket::fromPairs([]))->toThrow(InvalidArgumentException::class);
    });

    it('keeps the classic build working', function () {
        $ties = KnockoutBracket::build(['a', 'b', 'c', 'd']);

        expect(array_map(fn ($tie) => [$tie->a, $tie->b], array_values(array_filter($ties, fn ($tie) => $tie->round === 1))))->toBe([['a', 'd'], ['b', 'c']]);
    });
});

describe('DrawStage', function () {
    it('creates a league table in the order of the dragged confrontations', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::League);
        [$a, $b, $c, $d] = entryIds($entries);

        $created = app(DrawStage::class)->league($stage, [[$a, $b], [$c, $d], [$a, $c], [$d, $b], [$d, $a], [$b, $c]], false);
        $games = Game::query()->where('stage_id', $stage->id)->orderBy('number')->get();

        expect($created)->toBe(6)
            ->and($games->first()->round)->toBe(1)
            ->and([$games->first()->home_entry_id, $games->first()->away_entry_id])->toBe([$a, $b]);
    });

    it('refuses an incomplete or repeated league table and supports a return leg', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(3, StageType::League);
        [$a, $b, $c] = entryIds($entries);
        $draw = app(DrawStage::class);

        expect(fn () => $draw->league($stage, [[$a, $b], [$a, $b], [$a, $c]], false))->toThrow(InvalidArgumentException::class, 'Cada par');
        expect($draw->league($stage, [[$a, $b], [$a, $c], [$b, $c], [$b, $a], [$c, $a], [$c, $b]], true))->toBe(6);
        expect(Game::query()->where('stage_id', $stage->id)->where('round', 4)->exists())->toBeTrue();
    });

    it('creates the groups exactly as dragged and their round robin games', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(5, StageType::Groups);
        [$a, $b, $c, $d, $e] = entryIds($entries);

        $created = app(DrawStage::class)->groups($stage, [
            ['name' => 'Chave Azul', 'entries' => [$a, $c, $e]],
            ['name' => 'Chave Verde', 'entries' => [$b, $d]],
        ], false);

        $groups = Group::query()->where('stage_id', $stage->id)->orderBy('sequence')->get();
        expect($created)->toBe(4)
            ->and($groups->pluck('name')->all())->toBe(['Chave Azul', 'Chave Verde'])
            ->and($groups[0]->entries->pluck('id')->sort()->values()->all())->toBe(collect([$a, $c, $e])->sort()->values()->all())
            ->and(Game::query()->where('stage_id', $stage->id)->whereNotNull('group_id')->count())->toBe(4);
    });

    it('refuses repeated, missing and foreign teams, repeated names and small groups', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::Groups);
        [$a, $b, $c, $d] = entryIds($entries);
        $draw = app(DrawStage::class);

        expect(fn () => $draw->groups($stage, [['name' => 'A', 'entries' => [$a, $b]], ['name' => 'B', 'entries' => [$b, $c]]], false))->toThrow(InvalidArgumentException::class, 'mais de uma vez');
        expect(fn () => $draw->groups($stage, [['name' => 'A', 'entries' => [$a, $b]], ['name' => 'B', 'entries' => [$c, '01HZZZZZZZZZZZZZZZZZZZZZZZ']]], false))->toThrow(InvalidArgumentException::class, 'não estão confirmadas');
        expect(fn () => $draw->groups($stage, [['name' => 'A', 'entries' => [$a, $b]]], false))->toThrow(InvalidArgumentException::class, 'Todas as equipes');
        expect(fn () => $draw->groups($stage, [['name' => 'A', 'entries' => [$a, $b]], ['name' => ' a ', 'entries' => [$c, $d]]], false))->toThrow(InvalidArgumentException::class, 'nome diferente');
        expect(fn () => $draw->groups($stage, [['name' => 'A', 'entries' => [$a, $b, $c]], ['name' => 'B', 'entries' => [$d]]], false))->toThrow(InvalidArgumentException::class, 'pelo menos duas');

        expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(0);
    });

    it('does not count teams that are not confirmed and refuses a stage with games', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(3, StageType::Groups);
        Entry::query()->whereKey($entries[2]->id)->update(['status' => EntryStatus::Withdrawn]);
        [$a, $b] = entryIds($entries);
        $draw = app(DrawStage::class);

        expect($draw->groups($stage, [['name' => 'Única', 'entries' => [$a, $b]]], true))->toBe(2);
        expect(fn () => $draw->groups($stage, [['name' => 'Única', 'entries' => [$a, $b]]], false))->toThrow(InvalidArgumentException::class, 'já tem jogos');
    });

    it('creates the games of the informed bracket', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(6, StageType::Knockout);
        [$a, $b, $c, $d, $e, $f] = entryIds($entries);

        $created = app(DrawStage::class)->knockout($stage, [[$a, null], [$b, $c], [$d, $e], [null, $f]], 2);

        $games = Game::query()->where('stage_id', $stage->id)->orderBy('number')->get();
        $round1 = $games->where('round', 1);

        // 2 confrontos reais na 1ª rodada x 2 jogos + 2 na 2ª + 1 final, todos ida e volta.
        expect($created)->toBe(10)
            ->and($round1)->toHaveCount(4)
            ->and($games->where('round', 2))->toHaveCount(4)
            ->and($games->where('round', 3))->toHaveCount(2);

        $semi = $games->where('round', 2)->sortBy('number')->values();
        expect(collect([$semi[0]->home_entry_id, $semi[0]->away_entry_id])->filter()->all())->toContain($a)
            ->and(collect([$semi[2]->home_entry_id, $semi[2]->away_entry_id])->filter()->all())->toContain($f);
    });

    it('builds a bracket with only some of the confirmed teams when partial', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(6, StageType::Knockout);
        [$a, $b, $c, $d] = entryIds($entries);

        $created = app(DrawStage::class)->knockout($stage, [[$a, $b], [$c, $d]], 1, true);

        $teams = Game::query()->where('stage_id', $stage->id)->get()->flatMap(fn (Game $game) => [$game->home_entry_id, $game->away_entry_id])->filter()->unique();
        expect($created)->toBe(3)
            ->and($teams)->toHaveCount(4);
    });

    it('refuses a partial bracket with a repeated, unconfirmed or single team', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::Knockout);
        [$a, $b, $c] = entryIds($entries);
        $draw = app(DrawStage::class);

        expect(fn () => $draw->knockout($stage, [[$a, $a], [$b, $c]], 1, true))->toThrow(InvalidArgumentException::class, 'mais de uma vez');
        expect(fn () => $draw->knockout($stage, [[$a, null]], 1, true))->toThrow(InvalidArgumentException::class, 'pelo menos duas');
        expect(fn () => $draw->knockout($stage, [[$a, '01ARZ3NDEKTSV4RRFFQ69G5FAV']], 1, true))->toThrow(InvalidArgumentException::class, 'não estão confirmadas');
    });

    it('refuses a knockout that leaves a team out, repeats one, or is the wrong type', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::Knockout);
        [$a, $b, $c, $d] = entryIds($entries);
        $draw = app(DrawStage::class);

        expect(fn () => $draw->knockout($stage, [[$a, $b], [$c, null]], 1))->toThrow(InvalidArgumentException::class, 'Todas as equipes');
        expect(fn () => $draw->knockout($stage, [[$a, $b], [$a, $c]], 1))->toThrow(InvalidArgumentException::class, 'mais de uma vez');
        expect(fn () => $draw->groups($stage, [['name' => 'A', 'entries' => [$a, $b, $c, $d]]], false))->toThrow(InvalidArgumentException::class, 'não é de grupos');
        expect(fn () => $draw->knockout($stage, [[$a, $b], [$c, $d]], 3))->toThrow(InvalidArgumentException::class);
    });
});

describe('draw screen and route', function () {
    beforeEach(function () {
        CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
        $this->actingAs(panelUser());
    });

    it('opens the draw screen with the confirmed teams in seed order', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::Groups);

        $this->get("/painel/stages/{$stage->id}/draw")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Panel/Stages/Draw')
            ->where('stage.type', 'groups')
            ->has('entries', 4)
            ->where('entries.0.id', $entries[0]->id)
            ->where('entries.0.club_key', null)
            ->where('entries.0.sponsor_key', null)
            ->where('blocked', null)
            ->where('supported', true));
    });

    it('blocks the screen when the stage has games and allows league tables', function () {
        ['stage' => $stage] = fixtureDivision(4, StageType::Groups);
        app(DrawStage::class)->groups($stage, [['name' => 'A', 'entries' => Entry::query()->where('division_id', $stage->division_id)->pluck('id')->all()]], false);

        $this->get("/painel/stages/{$stage->id}/draw")->assertInertia(fn ($page) => $page->whereNot('blocked', null));

        ['stage' => $league] = fixtureDivision(4, StageType::League);
        $this->get("/painel/stages/{$league->id}/draw")->assertInertia(fn ($page) => $page->where('supported', true));
    });

    it('saves a groups draw and a knockout draw through the route', function () {
        ['stage' => $groups, 'entries' => $entries] = fixtureDivision(4, StageType::Groups);
        [$a, $b, $c, $d] = entryIds($entries);

        $this->post("/painel/stages/{$groups->id}/draw", ['double_round' => false, 'groups' => [['name' => 'Azul', 'entries' => [$a, $b]], ['name' => 'Verde', 'entries' => [$c, $d]]]])
            ->assertRedirect(route('panel.stages.show', $groups))->assertSessionHas('success');
        expect(Game::query()->where('stage_id', $groups->id)->count())->toBe(2);

        ['stage' => $knockout, 'entries' => $entries] = fixtureDivision(4, StageType::Knockout);
        [$a, $b, $c, $d] = entryIds($entries);

        $this->post("/painel/stages/{$knockout->id}/draw", ['legs' => 1, 'pairs' => [[$a, $c], [$b, $d]]])->assertSessionHas('success');
        expect(Game::query()->where('stage_id', $knockout->id)->count())->toBe(3);

        ['stage' => $league, 'entries' => $entries] = fixtureDivision(4, StageType::League);
        [$a, $b, $c, $d] = entryIds($entries);
        $this->post("/painel/stages/{$league->id}/draw", ['double_round' => false, 'pairs' => [[$a, $b], [$c, $d], [$a, $c], [$d, $b], [$d, $a], [$b, $c]]])
            ->assertRedirect(route('panel.stages.show', $league))->assertSessionHas('success');
        expect(Game::query()->where('stage_id', $league->id)->count())->toBe(6);
    });

    it('returns an error message and validation errors for a bad draw', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(4, StageType::Groups);

        $this->post("/painel/stages/{$stage->id}/draw", ['double_round' => false, 'groups' => [['name' => 'A', 'entries' => entryIds(array_slice($entries, 0, 2))]]])->assertSessionHas('error');
        $this->post("/painel/stages/{$stage->id}/draw", [])->assertSessionHasErrors(['groups', 'double_round']);
        expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(0);
    });

    it('lets read-only roles see the screen but not save', function () {
        ['stage' => $stage, 'entries' => $entries] = fixtureDivision(2, StageType::Knockout);
        $this->actingAs(panelUser(Role::Viewer));

        $this->get("/painel/stages/{$stage->id}/draw")->assertOk();
        $this->post("/painel/stages/{$stage->id}/draw", ['legs' => 1, 'pairs' => [entryIds($entries)]])->assertSessionHas('error');
        expect(Game::query()->where('stage_id', $stage->id)->count())->toBe(0);
    });
});
