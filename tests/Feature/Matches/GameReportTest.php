<?php

use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('baixa a escalação pré-partida em PDF para o operador', function () {
    ['game' => $game, 'home' => $home] = sheetGame();
    $user = panelUser(Role::Operator);

    $response = $this->actingAs($user)->get(route('panel.games.reports.lineup', $game));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="escalacao-'.$game->id.'.pdf"');
    expect($response->getContent())->toStartWith('%PDF-1.4')
        ->and($response->getContent())->toContain('REL')
        ->and($response->getContent())->toContain('EQUIPE:')
        ->and($response->getContent())->toContain('Titulares:');
});

it('inclui placar, escalações e eventos efetivos no relatório final', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);
    $user = panelUser(Role::Operator);

    $response = $this->actingAs($user)->get(route('panel.games.reports.final', $game));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('REL')
        ->and($response->getContent())->toContain('Placar final:')
        ->and($response->getContent())->toContain('LINHA DO TEMPO')
        ->and($response->getContent())->toContain('Gol');
});

it('permite visualizar a escalação no navegador sem forçar o download', function () {
    ['game' => $game] = sheetGame();

    $this->actingAs(panelUser(Role::Viewer))
        ->get(route('panel.games.reports.lineup', $game).'?visualizar=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="escalacao-'.$game->id.'.pdf"');
});

it('oferece o recorte de atividades sem dados de escalação', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);

    $response = $this->actingAs(panelUser(Role::Operator))->get(route('panel.games.reports.final', $game).'?tipo=activities');

    $response->assertOk();
    expect($response->getContent())->toContain('RESUMO DA PARTIDA')
        ->and($response->getContent())->toContain('LINHA DO TEMPO')
        ->and($response->getContent())->toContain('ATIVIDADES');
});

it('permite consultar o relatório a um membro do painel', function () {
    ['game' => $game] = sheetGame();

    $this->actingAs(panelUser(Role::Viewer))
        ->get(route('panel.games.reports.lineup', $game))
        ->assertOk();
});
