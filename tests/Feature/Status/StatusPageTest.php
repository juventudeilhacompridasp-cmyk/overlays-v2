<?php

use App\Models\User;
use App\Modules\Status\Actions\ManageIncidents;
use App\Modules\Status\Actions\RunProbes;
use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use App\Modules\Status\Models\Check;
use App\Modules\Status\Models\Component;
use App\Modules\Status\Models\Incident;
use App\Modules\Status\Probes\Probe;
use App\Modules\Status\Probes\ProbeResult;
use App\Modules\Status\Support\StatusComponents;
use App\Modules\Status\Support\StatusSummary;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Sonda de mentira: o resultado de cada chave vem de `$results` (true = ok, false = falha, null = pulada). */
class StatusFakeProbe implements Probe
{
    /** @var array<string, bool|null> */
    public static array $results = [];

    public function __construct(private string $key)
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->key;
    }

    public function run(): ProbeResult
    {
        return match (self::$results[$this->key] ?? true) {
            null => ProbeResult::skipped(),
            true => ProbeResult::ok(12),
            false => ProbeResult::fail('falhou'),
        };
    }
}

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'name' => 'Organização Secreta']));
    StatusFakeProbe::$results = [];
    Cache::flush();
    config(['platform.status.storage_disk' => '', 'platform.status.publish_disk' => null]);
});

function statusAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

function runFake(string $key = 'web'): void
{
    (new RunProbes)->handle([new StatusFakeProbe($key)]);
}

function webComponent(): Component
{
    return StatusComponents::ensure()->firstWhere('key', 'web');
}

describe('public page', function () {
    it('is public, lists the monitored components and never shows organization data', function () {
        $this->get('/status')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Status/Index')->where('status.overall', 'operational')->has('status.components', 5)->where('status.components.0.key', 'web')->has('status.components.0.days', 90));

        $json = $this->get('/status.json')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')->getContent();

        expect($json)->not->toContain('Organização Secreta')->and(json_decode($json, true))->toHaveKeys(['overall', 'overall_label', 'updated_at', 'components', 'incidents', 'maintenance']);
    });

    it('serves an Atom feed with escaped incident text', function () {
        app(ManageIncidents::class)->open('Falha <b>grave</b> & lenta', Impact::Minor, IncidentState::Investigating, [webComponent()->getKey()], 'Investigando <script>');

        $response = $this->get('/status.atom')->assertOk();

        expect($response->headers->get('Content-Type'))->toContain('application/atom+xml')
            ->and($response->getContent())->toContain('Falha &lt;b&gt;grave&lt;/b&gt; &amp; lenta')->not->toContain('<script>');
        simplexml_load_string($response->getContent()) ?: throw new RuntimeException('Atom inválido');
    });

    it('treats status.{base domain} as the platform host, not as an organization', function () {
        config(['platform.base_domain' => 'exemplo.test']);

        expect(OrganizationResolver::forHost('status.exemplo.test')->slug)->toBe(config('platform.default_organization_slug'));
        expect(fn () => OrganizationResolver::forHost('inexistente.exemplo.test'))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    });
});

describe('probes and automatic incidents', function () {
    it('opens an incident only after the consecutive failure threshold and resolves it after recovery', function () {
        StatusFakeProbe::$results = ['web' => false];

        runFake();
        runFake();
        expect(Incident::query()->count())->toBe(0)
            ->and(StatusSummary::build()['components'][0]['status'])->toBe('degraded');

        runFake();
        $incident = Incident::query()->firstOrFail();

        expect($incident->auto)->toBeTrue()->and($incident->impact)->toBe(Impact::Major)->and($incident->state)->toBe(IncidentState::Investigating)
            ->and($incident->components()->pluck('key')->all())->toBe(['web'])
            ->and(StatusSummary::build()['overall'])->toBe('outage');

        runFake(); // mais uma falha não abre outro incidente
        expect(Incident::query()->count())->toBe(1);

        StatusFakeProbe::$results = ['web' => true];
        runFake();
        expect($incident->fresh()->state)->toBe(IncidentState::Investigating);

        runFake();
        expect($incident->fresh()->state)->toBe(IncidentState::Resolved)->and($incident->fresh()->resolved_at)->not->toBeNull()
            ->and($incident->updates()->count())->toBe(2)
            ->and(StatusSummary::build()['overall'])->toBe('operational');
    });

    it('records nothing for skipped probes', function () {
        StatusFakeProbe::$results = ['web' => null];
        runFake();

        expect(Check::query()->count())->toBe(0);
    });

    it('does not open incidents during an active maintenance window', function () {
        app(ManageIncidents::class)->open('Manutenção', Impact::Maintenance, IncidentState::InProgress, [webComponent()->getKey()], 'Atualizando.', now()->subMinute(), now()->addHour());
        StatusFakeProbe::$results = ['web' => false];

        foreach (range(1, 4) as $_) {
            runFake();
        }

        expect(Incident::query()->where('auto', true)->count())->toBe(0)
            ->and(StatusSummary::build()['components'][0]['status'])->toBe('maintenance');
    });

    it('runs the real probes through the command, with fakes for the network', function () {
        Http::fake(['*' => Http::response('ok', 200)]);
        Storage::fake('statuspub');
        config(['platform.status.publish_disk' => 'statuspub']);

        $this->artisan('status:probe')->assertSuccessful();

        expect(Check::query()->where('ok', true)->count())->toBeGreaterThanOrEqual(3);
        Storage::disk('statuspub')->assertExists('status/status.json');
        expect(json_decode(Storage::disk('statuspub')->get('status/status.json'), true)['overall'])->toBe('operational');
    });

    it('fails the web probe when /up does not answer', function () {
        Http::fake(['*' => Http::response('erro', 500)]);

        expect((new App\Modules\Status\Probes\WebProbe)->run()->ok)->toBeFalse();
    });

    it('detects a stalled queue by the oldest unacknowledged heartbeat', function () {
        Cache::put('status:queue:sent', time() - 600, 3600);
        Cache::put('status:queue:beat', time() - 900, 3600);

        expect((new App\Modules\Status\Probes\QueueProbe)->run()->ok)->toBeFalse();

        Cache::put('status:queue:beat', time(), 3600);

        expect((new App\Modules\Status\Probes\QueueProbe)->run()->ok)->toBeTrue();
    });

    it('prunes old checks but keeps the retention window', function () {
        $component = webComponent();
        Check::query()->create(['component_id' => $component->getKey(), 'ok' => true, 'checked_at' => now()->subDays(120)]);
        Check::query()->create(['component_id' => $component->getKey(), 'ok' => true, 'checked_at' => now()->subDays(10)]);

        Artisan::call('status:prune');

        expect(Check::query()->count())->toBe(1);
    });
});

describe('summary', function () {
    it('computes the 90-day series and uptime per day', function () {
        $component = webComponent();
        $yesterday = now()->subDay()->setTime(12, 0);

        foreach ([true, true, true, false] as $ok) {
            Check::query()->create(['component_id' => $component->getKey(), 'ok' => $ok, 'checked_at' => $yesterday]);
        }

        $row = StatusSummary::build()['components'][0];

        expect($row['days'])->toHaveCount(90)
            ->and($row['days'][88])->toBe(['date' => now()->subDay()->toDateString(), 'uptime' => 75.0])
            ->and($row['days'][89]['uptime'])->toBeNull()
            ->and($row['uptime'])->toBe(75.0);
    });

    it('lists upcoming maintenance without changing the status before it starts', function () {
        app(ManageIncidents::class)->open('Atualização', Impact::Maintenance, IncidentState::Scheduled, [webComponent()->getKey()], 'Janela de manutenção.', now()->addDay(), now()->addDay()->addHour());

        $summary = StatusSummary::build();

        expect($summary['overall'])->toBe('operational')->and($summary['maintenance'])->toHaveCount(1)->and($summary['maintenance'][0]['state'])->toBe('scheduled');
    });
});

describe('platform admin', function () {
    it('is hidden (404) from anyone who is not a platform admin', function () {
        $this->get('/plataforma/status')->assertRedirect(); // sem login
        $this->actingAs(User::factory()->create())->get('/plataforma/status')->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/plataforma/status/incidents', [])->assertNotFound();
    });

    it('publishes, updates, resolves and removes an incident', function () {
        $admin = statusAdmin();
        $component = webComponent();

        $this->actingAs($admin)->get('/plataforma/status')->assertOk()->assertInertia(fn ($page) => $page->component('Platform/Status/Index')->has('components', 5));

        $this->actingAs($admin)->post('/plataforma/status/incidents', [
            'title' => 'Lentidão no painel', 'impact' => 'minor', 'state' => 'investigating', 'components' => [$component->getKey()], 'body' => 'Estamos investigando.',
        ])->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        expect($incident->auto)->toBeFalse()->and($this->get('/status.json')->json('incidents.0.title'))->toBe('Lentidão no painel')
            ->and($this->get('/status.json')->json('components.0.status'))->toBe('degraded');

        $this->actingAs($admin)->post("/plataforma/status/incidents/{$incident->getKey()}/updates", ['state' => 'resolved', 'body' => 'Normalizado.'])->assertRedirect();

        expect($incident->fresh()->state)->toBe(IncidentState::Resolved)->and($incident->fresh()->resolved_at)->not->toBeNull()
            ->and($incident->updates()->count())->toBe(2);

        $this->actingAs($admin)->delete("/plataforma/status/incidents/{$incident->getKey()}")->assertRedirect();
        expect(Incident::query()->count())->toBe(0);
    });

    it('schedules maintenance and validates the form', function () {
        $admin = statusAdmin();
        $component = webComponent();

        $this->actingAs($admin)->post('/plataforma/status/incidents', ['title' => '', 'impact' => 'minor', 'state' => 'investigating', 'components' => [], 'body' => ''])
            ->assertSessionHasErrors(['title', 'components', 'body']);

        $this->actingAs($admin)->post('/plataforma/status/incidents', [
            'title' => 'Atualização do Reverb', 'impact' => 'maintenance', 'state' => 'scheduled', 'components' => [$component->getKey()], 'body' => 'Breve interrupção.',
            'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();
        expect($incident->isMaintenance())->toBeTrue()->and($incident->state)->toBe(IncidentState::Scheduled)->and($incident->ends_at)->not->toBeNull();

        $this->actingAs($admin)->post('/plataforma/status/incidents', [
            'title' => 'Fim antes do início', 'impact' => 'maintenance', 'state' => 'scheduled', 'components' => [$component->getKey()], 'body' => 'x',
            'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->toDateTimeString(),
        ])->assertSessionHasErrors('ends_at');
    });

    it('coerces a state that does not fit the kind of notice', function () {
        $incident = app(ManageIncidents::class)->open('Teste', Impact::Minor, IncidentState::Scheduled, [webComponent()->getKey()], 'x');

        expect($incident->state)->toBe(IncidentState::Investigating);

        $updated = app(ManageIncidents::class)->update($incident, IncidentState::Completed, 'y');

        expect($updated->state)->toBe(IncidentState::Investigating);
    });
});
