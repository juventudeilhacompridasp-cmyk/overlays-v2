<?php

use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameSheetRead;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\SheetImportSettings;
use App\Modules\Matches\Reading\SheetReadFailed;
use App\Modules\Matches\Reading\StructuredSheetReader;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'opt_in_features' => ['sheet_import']]));
    Storage::fake('s3-private');
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.url' => 'https://api.anthropic.test/v1/messages']);
});

const READ_CSV = <<<'CSV'
Tipo;Equipe;Nome;Camisa;Minuto;Valor
time;casa;Leões;;;
time;fora;Águias;;;
placar;casa;;;;2
placar;fora;;;;1
escalação;casa;Fulano de Tal;10;;titular
escalacao;casa;Beltrano;12;;reserva
gol;casa;Fulano de Tal;10;23;
gol;fora;Cicrano;9;31;
amarelo;fora;Cicrano;9;40;
substituicao;casa;Beltrano;12;60;Fulano de Tal
observacao;;;;;Jogo começou atrasado
CSV;

function sheetFor(Game $game, UploadedFile $file): GameSheetUpload
{
    test()->actingAs(panelUser(Role::Operator))->post(route('panel.games.uploads.store', $game), ['file' => $file])->assertSessionHas('success');

    return GameSheetUpload::query()->latest('created_at')->firstOrFail();
}

function requestRead(GameSheetUpload $upload, Role $role = Role::Operator)
{
    return test()->actingAs(panelUser($role))->post(route('panel.games.uploads.read', $upload));
}

function visionAnswer(array $sheet): array
{
    return ['content' => [['type' => 'text', 'text' => "Aqui está:\n```json\n".json_encode($sheet)."\n```"]]];
}

function xlsxBytes(array $rows): string
{
    $strings = [];
    $sheet = '';

    foreach ($rows as $r => $row) {
        $sheet .= '<row r="'.($r + 1).'">';

        foreach ($row as $c => $value) {
            $ref = chr(65 + $c).($r + 1);

            if (is_int($value)) {
                $sheet .= "<c r=\"{$ref}\"><v>{$value}</v></c>";
            } else {
                $strings[] = $value;
                $sheet .= "<c r=\"{$ref}\" t=\"s\"><v>".(count($strings) - 1).'</v></c>';
            }
        }

        $sheet .= '</row>';
    }

    $shared = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s).'</t></si>', $strings)).'</sst>';
    $path = tempnam(sys_get_temp_dir(), 'x');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', $shared);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

describe('structured reading (CSV/XLSX, no AI)', function () {
    it('reads the CSV straight away, without calling any external service', function () {
        Http::fake();
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->createWithContent('sumula.csv', READ_CSV));

        requestRead($upload)->assertSessionHas('success');

        $upload->refresh();
        $data = $upload->extracted;

        expect($upload->status)->toBe(SheetUploadStatus::Read)
            ->and($data['home_team'])->toBe('Leões')
            ->and($data['away_team'])->toBe('Águias')
            ->and([$data['home_score'], $data['away_score']])->toBe([2, 1])
            ->and($data['lineups'])->toHaveCount(2)
            ->and($data['lineups'][0])->toMatchArray(['team' => 'home', 'name' => 'Fulano de Tal', 'shirt' => 10, 'starter' => true])
            ->and($data['lineups'][1]['starter'])->toBeFalse()
            ->and(collect($data['events'])->pluck('type')->all())->toBe(['goal', 'goal', 'yellow_card', 'substitution'])
            ->and($data['events'][3])->toMatchArray(['name' => 'Beltrano', 'detail' => 'Fulano de Tal', 'minute' => '60'])
            ->and($data['notes'])->toBe('Jogo começou atrasado')
            ->and($data['confidence'])->toBe(1.0);

        Http::assertNothingSent();
        expect(GameSheetRead::query()->where('reader', 'structured')->count())->toBe(1);
    });

    it('works even with the AI switched off and without an API key', function () {
        config(['services.anthropic.key' => null]);
        CurrentOrganization::get()->update(['settings' => ['sheet_import' => ['ai_enabled' => false, 'max_reads_per_game' => 1]]]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->createWithContent('sumula.csv', READ_CSV));

        requestRead($upload)->assertSessionHas('success');

        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Read);
    });

    it('reads a comma-separated file and an XLSX', function () {
        $reader = app(StructuredSheetReader::class);

        $csv = $reader->read("tipo,equipe,nome,camisa,minuto,valor\nplacar,casa,,,,3\nplacar,fora,,,,0\n", 'text/csv');
        $xlsx = $reader->read(xlsxBytes([
            ['tipo', 'equipe', 'nome', 'camisa', 'minuto', 'valor'],
            ['time', 'mandante', 'Leões', '', '', ''],
            ['placar', 'casa', '', '', '', 4],
            ['gol', 'casa', 'Fulano', 10, 12, ''],
        ]), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        expect([$csv->homeScore, $csv->awayScore])->toBe([3, 0])
            ->and($xlsx->homeTeam)->toBe('Leões')
            ->and($xlsx->homeScore)->toBe(4)
            ->and($xlsx->events[0])->toMatchArray(['type' => 'goal', 'name' => 'Fulano', 'shirt' => 10, 'minute' => '12']);
    });

    it('fails with a clear message when the header is not the template', function () {
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->createWithContent('outra.csv', "equipe;gols\nA;2\n"));

        requestRead($upload);

        $upload->refresh();
        expect($upload->status)->toBe(SheetUploadStatus::Failed)
            ->and($upload->error)->toContain('Cabeçalho não reconhecido');
    });

    it('rejects a corrupted XLSX', function () {
        expect(fn () => app(StructuredSheetReader::class)->read('isto não é um zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->toThrow(SheetReadFailed::class);
    });

    it('offers the template as a CSV the reader understands', function () {
        $response = $this->actingAs(panelUser(Role::Viewer))->get(route('panel.sheet-import.template'));

        $response->assertOk();
        $sheet = app(StructuredSheetReader::class)->read($response->getContent(), 'text/csv');

        expect($sheet->homeScore)->toBe(2)
            ->and($sheet->awayScore)->toBe(1)
            ->and($sheet->events)->not->toBeEmpty();
    });
});

describe('reading a photo or PDF with AI', function () {
    it('sends the file to the provider, keeps only the schema and drops anything that looks like a document', function () {
        Http::fake(['api.anthropic.test/*' => Http::response(visionAnswer([
            'home_team' => 'Leões', 'away_team' => 'Águias', 'home_score' => 2, 'away_score' => '1',
            'lineups' => [
                ['team' => 'home', 'name' => 'Fulano 123.456.789-09', 'shirt' => 10, 'starter' => true, 'confidence' => 1.7, 'cpf' => '12345678909'],
                ['team' => 'nowhere', 'name' => 'Descartado', 'shirt' => 1],
            ],
            'events' => [
                ['type' => 'goal', 'team' => 'home', 'name' => 'Fulano', 'shirt' => 10, 'minute' => '23', 'confidence' => 0.9],
                ['type' => 'penalty_kick_weird', 'team' => 'away', 'name' => null, 'shirt' => 'abc', 'minute' => '45+2'],
            ],
            'notes' => 'Telefone 11 98888-7777 do árbitro',
            'confidence' => 0.8,
            'address' => 'Rua X, 10',
        ]))]);

        $game = Game::factory()->create();
        $upload = sheetFor($game, UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload)->assertSessionHas('success');

        $upload->refresh();
        $data = $upload->extracted;

        expect($upload->status)->toBe(SheetUploadStatus::Read)
            ->and($data['away_score'])->toBe(1)
            ->and($data['lineups'])->toHaveCount(1)
            ->and($data['lineups'][0]['name'])->toBe('Fulano [removido]')
            ->and($data['lineups'][0]['confidence'])->toBe(1.0)
            ->and($data['lineups'][0])->not->toHaveKey('cpf')
            ->and($data['events'][1])->toMatchArray(['type' => 'other', 'shirt' => null, 'minute' => '45+2'])
            ->and($data['notes'])->toBe('Telefone [removido] do árbitro')
            ->and($data)->not->toHaveKey('address');

        Http::assertSent(function (Request $request) {
            $content = $request['messages'][0]['content'];

            return $request->hasHeader('x-api-key', 'test-key')
                && $request['model'] === config('services.anthropic.model')
                && $content[0]['type'] === 'document'
                && $content[0]['source']['media_type'] === 'application/pdf'
                && str_contains($content[1]['text'], 'Mandante:')
                && str_contains($request['system'], 'NÃO copie CPF');
        });
        expect(GameSheetRead::query()->where('reader', 'vision')->count())->toBe(1);
    });

    it('sends photos as an image block', function () {
        Http::fake(['api.anthropic.test/*' => Http::response(visionAnswer(['home_score' => 1, 'away_score' => 0]))]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->image('foto.jpg', 600, 800));

        requestRead($upload);

        Http::assertSent(fn (Request $request) => $request['messages'][0]['content'][0]['type'] === 'image');
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Read);
    });

    it('marks the upload as failed with a readable message when the provider errors out', function () {
        Http::fake(['api.anthropic.test/*' => Http::response(['error' => 'x'], 529)]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload);

        $upload->refresh();
        expect($upload->status)->toBe(SheetUploadStatus::Failed)
            ->and($upload->error)->toContain('HTTP 529')
            ->and($upload->extracted)->toBeNull();
    });

    it('marks the upload as failed when the answer is not understandable', function () {
        Http::fake(['api.anthropic.test/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Não consegui ler.']]])]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload);

        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Failed)
            ->and($upload->error)->toContain('entender a resposta');
    });

    it('allows reading again after a failure', function () {
        Http::fake(['api.anthropic.test/*' => Http::sequence()->push(['error' => 'x'], 500)->push(visionAnswer(['home_score' => 3, 'away_score' => 3]))]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload);
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Failed);

        requestRead($upload);
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Read)
            ->and($upload->error)->toBeNull();
    });
});

describe('limits and switches', function () {
    it('does not call the provider when the organization turned the AI off', function () {
        Http::fake();
        CurrentOrganization::get()->update(['settings' => ['sheet_import' => ['ai_enabled' => false]]]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload)->assertSessionHas('error');

        Http::assertNothingSent();
        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Uploaded)
            ->and(GameSheetRead::query()->count())->toBe(0);
    });

    it('does not call the provider without an API key', function () {
        Http::fake();
        config(['services.anthropic.key' => null]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload)->assertSessionHas('error');

        Http::assertNothingSent();
    });

    it('stops at the reads-per-game limit set by the organization', function () {
        Http::fake(['api.anthropic.test/*' => Http::response(visionAnswer(['home_score' => 1, 'away_score' => 1]))]);
        CurrentOrganization::get()->update(['settings' => ['sheet_import' => ['ai_enabled' => true, 'max_reads_per_game' => 2]]]);
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('sumula.pdf', 100, 'application/pdf'));

        requestRead($upload)->assertSessionHas('success');
        requestRead($upload)->assertSessionHas('success');
        requestRead($upload)->assertSessionHas('error');

        Http::assertSentCount(2);
    });

    it('never lets the organization go above the platform ceiling', function () {
        config(['platform.sheet_import.max_reads_per_game_ceiling' => 2]);
        CurrentOrganization::get()->update(['settings' => ['sheet_import' => ['ai_enabled' => true, 'max_reads_per_game' => 50]]]);

        expect(SheetImportSettings::maxReadsPerGame())->toBe(2);
    });

    it('stops at the monthly limit of the organization, counting only this month', function () {
        Http::fake(['api.anthropic.test/*' => Http::response(visionAnswer(['home_score' => 1, 'away_score' => 0]))]);
        config(['platform.sheet_import.monthly_reads' => 1]);
        $old = GameSheetRead::query()->create(['game_id' => Game::factory()->create()->getKey(), 'reader' => 'vision']);
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();

        $first = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'));
        $second = sheetFor(Game::factory()->create(), UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'));

        requestRead($first)->assertSessionHas('success');
        requestRead($second)->assertSessionHas('error');

        expect($second->refresh()->status)->toBe(SheetUploadStatus::Uploaded);
    });

    it('does not read a sheet that is being read or was already reviewed', function () {
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->createWithContent('sumula.csv', READ_CSV));

        $upload->update(['status' => SheetUploadStatus::Reading]);
        requestRead($upload)->assertSessionHas('error');

        $upload->update(['status' => SheetUploadStatus::Reviewed]);
        requestRead($upload)->assertSessionHas('error');
    });
});

describe('access', function () {
    it('lets only those who operate games ask for a reading', function () {
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->createWithContent('sumula.csv', READ_CSV));

        requestRead($upload, Role::Viewer);

        expect($upload->refresh()->status)->toBe(SheetUploadStatus::Uploaded);
    });

    it('needs the sheet_import feature', function () {
        $upload = sheetFor(Game::factory()->create(), UploadedFile::fake()->createWithContent('sumula.csv', READ_CSV));
        CurrentOrganization::get()->update(['plan' => Plan::Pro->value, 'features' => null, 'opt_in_features' => null]);

        requestRead($upload, Role::Admin)->assertForbidden();
    });

    it('lets the administrator save the AI switch and the per-game limit, and nobody else', function () {
        $payload = ['ai_enabled' => false, 'max_reads_per_game' => 5];

        $this->actingAs(panelUser(Role::Organizer))->patch(route('panel.sheet-import.settings'), $payload);
        expect((CurrentOrganization::get()->fresh()->settings ?? [])['sheet_import'] ?? null)->toBeNull();

        $this->actingAs(panelUser(Role::Admin))->patch(route('panel.sheet-import.settings'), $payload)->assertSessionHas('success');
        expect(CurrentOrganization::get()->fresh()->settings['sheet_import'])->toBe(['ai_enabled' => false, 'max_reads_per_game' => 5]);

        $this->actingAs(panelUser(Role::Admin))->patch(route('panel.sheet-import.settings'), ['ai_enabled' => true, 'max_reads_per_game' => 99])->assertSessionHasErrors('max_reads_per_game');
    });

    it('tells the screen whether the AI is on and how many reads are left', function () {
        $game = Game::factory()->create();
        sheetFor($game, UploadedFile::fake()->createWithContent('sumula.csv', READ_CSV));

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.games.uploads', $game))->assertInertia(fn ($page) => $page
            ->where('ai.enabled', true)
            ->where('ai.available', true)
            ->where('ai.reads_left', 3)
            ->where('ai.can_configure', true));

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.games.uploads', $game))->assertInertia(fn ($page) => $page->where('ai.can_configure', false));
    });
});
