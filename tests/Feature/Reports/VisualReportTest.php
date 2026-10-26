<?php

use App\Modules\Competitions\Models\Division;
use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Reports\Pdf\PdfDocument;
use App\Modules\Reports\Pdf\ReportPdf;
use App\Modules\Reports\Support\ChampionshipReport;
use App\Modules\Reports\Support\GameReport;
use App\Modules\Reports\Support\ReportIcons;
use App\Modules\Reports\Support\ReportSections;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/** Texto de todas as páginas do PDF (os conteúdos saem comprimidos), em UTF-8. */
function pdfText(string $pdf): string
{
    preg_match_all("/stream\n(.*?)\nendstream/s", $pdf, $matches);
    $text = '';

    foreach ($matches[1] as $stream) {
        $inflated = @gzuncompress($stream);
        $text .= $inflated === false ? '' : $inflated;
    }

    return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
}

/** @return list<array<string, mixed>> blocos de uma seção do documento */
function sectionBlocks(array $document, string $key): array
{
    return collect($document['sections'])->firstWhere('key', $key)['blocks'];
}

it('writes a valid PDF with accents, several pages and a correct cross-reference table', function () {
    $pdf = new PdfDocument('Relatório de ação');
    $pdf->addPage();
    $pdf->text(40, 60, 'Ação, coração e pênalti — 2º tempo', 12);
    $pdf->wedge(100, 200, 50, 0, 0.4, '#ff0000');
    $pdf->icon(ReportIcons::path('trophy'), 10, 10, 24, '#000000');
    $pdf->withOpacity(0.5, fn (PdfDocument $doc) => $doc->rect(10, 10, 50, 50, '#00ff00', null, 6));
    $pdf->addPage();
    $pdf->text(40, 60, 'Segunda página 🎉', 12);
    $out = $pdf->output();

    expect($out)->toStartWith('%PDF-1.4')->toEndWith("%%EOF\n")
        ->and(substr_count($out, '/Type /Page '))->toBe(2)
        ->and($out)->toContain('/Encoding /WinAnsiEncoding')
        ->and(pdfText($out))->toContain('Ação, coração e pênalti — 2º tempo')
        ->and(pdfText($out))->toContain('(Segunda página)');

    // cada entrada da xref aponta para o início do objeto correspondente
    preg_match('/startxref\n(\d+)/', $out, $start);
    $xref = substr($out, (int) $start[1]);
    preg_match_all('/^(\d{10}) 00000 n $/m', $xref, $offsets);
    foreach ($offsets[1] as $index => $offset) {
        expect(substr($out, (int) $offset, strlen(($index + 1).' 0 obj')))->toBe(($index + 1).' 0 obj');
    }
});

it('measures, fits and wraps text with the Helvetica metrics', function () {
    expect(PdfDocument::width('ação', 10))->toBe(PdfDocument::width('acao', 10))
        ->and(PdfDocument::width('W', 10, true))->toBeGreaterThan(PdfDocument::width('i', 10, true))
        ->and(PdfDocument::fit('Um nome de equipe muito comprido', 60, 10))->toEndWith('…')
        ->and(PdfDocument::width(PdfDocument::fit('Um nome de equipe muito comprido', 60, 10), 10))->toBeLessThanOrEqual(60)
        ->and(PdfDocument::wrap('uma frase com várias palavras para quebrar', 80, 10))->toHaveCount(3);
});

it('only uses the fixed icon vocabulary', function () {
    foreach (ReportIcons::KEYS as $key) {
        expect(ReportIcons::path($key))->toMatch('/^(?:[MLCZ]|-?\d+(?:\.\d+)?|\s)+$/');
    }

    expect(ReportIcons::for(['ball', 'inventado']))->toHaveKeys(['ball', 'chart']);
});

it('picks sections in catalog order and treats an empty choice as all', function () {
    expect(ReportSections::pick(ReportKind::Game, ['timeline', 'overview', 'xx']))->toBe(['overview', 'timeline'])
        ->and(ReportSections::pick(ReportKind::Game, []))->toBe(array_keys(ReportSections::GAME))
        ->and(ReportSections::pick(ReportKind::Championship, 'x'))->toBe(array_keys(ReportSections::CHAMPIONSHIP));
});

it('builds the match report from valid events only', function () {
    ['game' => $game, 'home' => $home, 'away' => $away, 'hp' => $hp, 'ap' => $ap] = sheetGame();
    $record = app(RecordGameEvent::class);
    app(ChangeGamePeriod::class)->handle($game, true, 1);
    $record->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);
    $wrong = $record->handle($game, GameEventType::Goal, $away->id, $ap[0], minute: 20);
    app(VoidGameEvent::class)->handle($game, $wrong);
    $record->handle($game, GameEventType::YellowCard, $away->id, $ap[1], minute: 30);
    $record->handle($game, GameEventType::Substitution, $home->id, $hp[1], minute: 40, secondaryPersonId: $hp[12]);

    $document = GameReport::build($game->fresh());

    expect($document['hero']['home']['score'])->toBe(1)->and($document['hero']['away']['score'])->toBe(0)
        ->and($document['private'])->toBeTrue()
        ->and(collect($document['sections'])->pluck('key')->all())->toBe(array_keys(ReportSections::GAME));

    $timeline = sectionBlocks($document, 'timeline')[0];
    expect($timeline['type'])->toBe('timeline')
        ->and(collect($timeline['items'])->pluck('title')->all())->toBe(['Início · 1º tempo', 'Gol', 'Cartão amarelo', 'Substituição'])
        ->and($timeline['items'][1]['side'])->toBe('home');

    $versus = sectionBlocks($document, 'comparison')[0];
    expect(collect($versus['rows'])->firstWhere('label', 'Gols'))->toMatchArray(['home' => 1, 'away' => 0])
        ->and(collect($versus['rows'])->firstWhere('label', 'Cartões amarelos'))->toMatchArray(['home' => 0, 'away' => 1]);

    $periods = sectionBlocks($document, 'periods')[0];
    expect($periods['categories'][0])->toBe(['label' => '1º tempo', 'values' => [1, 0]]);

    $lineups = sectionBlocks($document, 'lineups');
    expect($lineups[0]['type'])->toBe('table')->and($lineups[0]['rows'])->toHaveCount(14);

    // nada de IDs no documento
    $json = json_encode($document);
    expect($json)->not->toContain((string) $hp[0])->and($json)->not->toContain((string) $game->id);
});

it('hides cards and uses public names in the public version', function () {
    ['game' => $game, 'away' => $away, 'ap' => $ap] = sheetGame();
    Person::query()->whereKey($ap[1])->update(['name' => 'Carlos Menor Silva', 'nickname' => 'Carlão', 'birth_date' => now()->subYears(15)]);
    app(RecordGameEvent::class)->handle($game, GameEventType::YellowCard, $away->id, $ap[1], minute: 30);
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $away->id, $ap[1], minute: 31);

    $public = GameReport::build($game->fresh(), null, true);
    $titles = collect(sectionBlocks($public, 'timeline')[0]['items'])->pluck('title')->all();
    $json = json_encode($public, JSON_UNESCAPED_UNICODE);

    expect($titles)->toBe(['Gol'])
        ->and($json)->toContain('Carlos S.')->and($json)->not->toContain('Carlão')->and($json)->not->toContain('Menor')
        ->and($json)->not->toContain('Cartões amarelos')
        ->and($public['private'])->toBeFalse();

    // com os cartões públicos ligados, entram
    $organization = CurrentOrganization::get();
    $organization->update(['settings' => ['portal' => ['game_cards' => true]]]);
    $titles = collect(sectionBlocks(GameReport::build($game->fresh(), null, true), 'timeline')[0]['items'])->pluck('title')->all();
    expect($titles)->toBe(['Cartão amarelo', 'Gol']);
});

it('builds the championship report with standings, scorers and results', function () {
    ['stage' => $stage] = fixtureDivision(4);
    app(GenerateStageFixtures::class)->handle($stage);
    $games = Game::query()->where('stage_id', $stage->id)->orderBy('round')->orderBy('number')->get();
    $first = $games->first();
    $roster = RosterEntry::factory()->create(['entry_id' => $first->home_entry_id, 'shirt_number' => 9]);
    app(SaveGameLineup::class)->handle($first, $first->homeEntry, [$roster->person_id], []);
    app(RecordGameEvent::class)->handle($first, GameEventType::Goal, $first->home_entry_id, $roster->person_id, minute: 5);
    app(RecordGameEvent::class)->handle($first, GameEventType::Goal, $first->home_entry_id, $roster->person_id, minute: 50);
    app(FinishGame::class)->handle($first->fresh());

    $document = ChampionshipReport::build(Division::query()->findOrFail($stage->division_id));

    $kpis = sectionBlocks($document, 'overview')[0]['items'];
    expect(collect($kpis)->firstWhere('label', 'Jogos realizados')['value'])->toBe('1')
        ->and(collect($kpis)->firstWhere('label', 'Gols marcados')['value'])->toBe('2');

    $table = sectionBlocks($document, 'standings')[0];
    expect($table['type'])->toBe('table')->and($table['rows'])->toHaveCount(4)
        ->and($table['rows'][0]['cells']['points'])->toBe('3')->and($table['rows'][0]['highlight'])->toBeTrue();

    $scorers = sectionBlocks($document, 'scorers')[0];
    expect($scorers['type'])->toBe('ranking')->and($scorers['items'][0]['value'])->toBe(2)->and($scorers['items'][0]['position'])->toBe(1);

    $results = sectionBlocks($document, 'results');
    expect($results[0]['items'][0]['value'])->toBe('2 x 0');

    expect(sectionBlocks($document, 'knockout')[0]['type'])->toBe('note');

    expect(pdfText(ReportPdf::render($document)))->toContain('Classificação')->toContain('Artilharia')->toContain('Página 1 de');
});

it('downloads the illustrated PDFs from the panel and keeps them private', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);
    $viewer = panelUser(Role::Viewer);

    $response = $this->actingAs($viewer)->get(route('panel.visual-reports.game', $game).'?secoes=overview,timeline');

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename="relatorio-partida-');
    $text = pdfText($response->getContent());
    expect($text)->toContain('Visão geral')->toContain('Linha do tempo')->not->toContain('Escalações')->toContain('USO INTERNO');

    $this->actingAs($viewer)->get(route('panel.visual-reports.championship', $game->division_id))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($viewer)->get(route('panel.visual-reports.index', ['tipo' => 'game', 'jogo' => $game->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Panel/VisualReports/Index')->where('kind', 'game')->where('document.hero.home.score', 1)->has('games', 1));
});

it('refuses other organizations and the plan without reports', function () {
    ['game' => $game] = sheetGame();
    $user = panelUser(Role::Admin);
    $other = Organization::factory()->create();
    CurrentOrganization::set($other);
    ['game' => $foreign] = sheetGame();
    CurrentOrganization::set(Organization::query()->where('slug', config('platform.default_organization_slug'))->first());

    $this->actingAs($user)->get(route('panel.visual-reports.game', $foreign))->assertNotFound();

    CurrentOrganization::get()->update(['plan' => 'basic', 'features' => []]);
    $this->actingAs($user)->get(route('panel.visual-reports.game', $game))->assertForbidden();
});

it('does not count voided events in the PDF', function () {
    ['game' => $game, 'home' => $home, 'hp' => $hp] = sheetGame();
    $goal = app(RecordGameEvent::class)->handle($game, GameEventType::Goal, $home->id, $hp[0], minute: 12);
    app(VoidGameEvent::class)->handle($game, $goal);

    $document = GameReport::build($game->fresh(), ['timeline']);

    expect(GameEvent::query()->count())->toBe(2)
        ->and(sectionBlocks($document, 'timeline')[0]['type'])->toBe('note');
});
