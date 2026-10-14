<?php

use App\Models\User;
use App\Modules\Marketing\Models\Lead;
use App\Modules\Marketing\Support\LandingMetrics;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    config(['platform.landing.analytics' => true]);
});

function metricTotal(string $metric, string $dimension = ''): int
{
    return (int) DB::table('marketing_metrics')->where('metric', $metric)->where('dimension', $dimension)->sum('total');
}

function metricAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

describe('recording', function () {
    it('counts valid events, once per pair in a batch, and accumulates across requests', function () {
        $events = [['metric' => 'visit', 'dimension' => ''], ['metric' => 'source', 'dimension' => 'search'], ['metric' => 'source', 'dimension' => 'search'], ['metric' => 'device', 'dimension' => 'mobile']];

        expect(LandingMetrics::record($events))->toBe(3)
            ->and(metricTotal('visit'))->toBe(1)->and(metricTotal('source', 'search'))->toBe(1)->and(metricTotal('device', 'mobile'))->toBe(1);

        LandingMetrics::record($events);

        expect(metricTotal('visit'))->toBe(2)->and(metricTotal('source', 'search'))->toBe(2)->and(DB::table('marketing_metrics')->count())->toBe(3);
    });

    it('drops anything outside the closed list', function () {
        $accepted = LandingMetrics::record([
            ['metric' => 'visit', 'dimension' => 'x'],
            ['metric' => 'source', 'dimension' => 'https://exemplo.test/privado'],
            ['metric' => 'section', 'dimension' => 'inexistente'],
            ['metric' => 'ip', 'dimension' => '200.1.1.1'],
            ['metric' => ['x'], 'dimension' => 5],
            ['metric' => 'cta', 'dimension' => 'hero'],
        ]);

        expect($accepted)->toBe(1)->and(DB::table('marketing_metrics')->count())->toBe(1)->and(metricTotal('cta', 'hero'))->toBe(1);
    });

    it('only keeps the first ten events of a request', function () {
        $events = array_merge(
            array_map(fn (string $dimension) => ['metric' => 'section', 'dimension' => $dimension], ['galeria', 'pacotes', 'faq', 'contato']),
            array_map(fn (string $dimension) => ['metric' => 'cta', 'dimension' => $dimension], ['menu', 'hero', 'contato']),
            [['metric' => 'device', 'dimension' => 'mobile'], ['metric' => 'device', 'dimension' => 'desktop'], ['metric' => 'visit', 'dimension' => ''], ['metric' => 'source', 'dimension' => 'direct']],
        );

        expect(LandingMetrics::record($events))->toBe(LandingMetrics::MAX_EVENTS)->and(metricTotal('source', 'direct'))->toBe(0);
    });

    it('stores only counters: no ip, agent, identifier or url', function () {
        expect(Schema::getColumnListing('marketing_metrics'))->toBe(['day', 'metric', 'dimension', 'total']);
    });

    it('groups the days by the São Paulo calendar', function () {
        $this->travelTo(Carbon::parse('2026-10-15 02:30:00', 'UTC')); // 23:30 de 14/10 em São Paulo

        LandingMetrics::record([['metric' => 'visit', 'dimension' => '']]);

        expect(DB::table('marketing_metrics')->value('day'))->toBe('2026-10-14');
    });
});

describe('endpoint', function () {
    it('accepts events with 204 and rejects malformed bodies', function () {
        $this->postJson('/produto/medicao', ['events' => [['metric' => 'visit', 'dimension' => ''], ['metric' => 'source', 'dimension' => 'social']]])->assertNoContent();

        expect(metricTotal('visit'))->toBe(1)->and(metricTotal('source', 'social'))->toBe(1);

        $this->postJson('/produto/medicao', [])->assertUnprocessable();
        $this->postJson('/produto/medicao', ['events' => 'visit'])->assertUnprocessable();
        $this->postJson('/produto/medicao', ['events' => array_fill(0, 11, ['metric' => 'visit', 'dimension' => ''])])->assertUnprocessable();
        $this->postJson('/produto/medicao', ['events' => [['metric' => str_repeat('a', 21), 'dimension' => '']]])->assertUnprocessable();
    });

    it('silently ignores values outside the list', function () {
        $this->postJson('/produto/medicao', ['events' => [['metric' => 'source', 'dimension' => 'qualquer']]])->assertNoContent();

        expect(DB::table('marketing_metrics')->count())->toBe(0);
    });

    it('is off (404) when the measurement is disabled, and the page says so', function () {
        config(['platform.landing.analytics' => false]);

        $this->postJson('/produto/medicao', ['events' => [['metric' => 'visit', 'dimension' => '']]])->assertNotFound();
        $this->get('/produto')->assertInertia(fn ($page) => $page->where('analytics', false));

        expect(DB::table('marketing_metrics')->count())->toBe(0);

        config(['platform.landing.analytics' => true]);
        $this->get('/produto')->assertInertia(fn ($page) => $page->where('analytics', true));
    });

    it('is rate limited', function () {
        foreach (range(1, 30) as $_) {
            $this->postJson('/produto/medicao', ['events' => [['metric' => 'visit', 'dimension' => '']]])->assertNoContent();
        }

        $this->postJson('/produto/medicao', ['events' => [['metric' => 'visit', 'dimension' => '']]])->assertStatus(429);
    });
});

describe('summary', function () {
    it('fills every day, computes totals, conversion and shares of the measured visits', function () {
        $this->travelTo(Carbon::parse('2026-10-15 15:00:00', 'UTC'));
        $row = fn (string $day, string $metric, string $dimension, int $total) => DB::table('marketing_metrics')->insert(['day' => $day, 'metric' => $metric, 'dimension' => $dimension, 'total' => $total]);

        $row('2026-10-15', 'visit', '', 60);
        $row('2026-10-13', 'visit', '', 40);
        $row('2026-10-15', 'source', 'search', 50);
        $row('2026-10-15', 'source', 'direct', 10);
        $row('2026-10-13', 'source', 'direct', 40);
        $row('2026-10-15', 'cta', 'hero', 25);
        $row('2026-09-01', 'visit', '', 999); // fora do período

        foreach (['2026-10-15 13:00:00', '2026-10-15 14:00:00'] as $createdAt) {
            Lead::query()->create(['name' => 'A', 'email' => 'a@a.test', 'message' => 'mensagem longa o bastante', 'status' => 'new', 'consent_at' => now(), 'created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        $summary = LandingMetrics::summary(7);

        expect($summary['days'])->toBe(7)->and($summary['from'])->toBe('2026-10-09')->and($summary['to'])->toBe('2026-10-15')
            ->and($summary['daily'])->toHaveCount(7)
            ->and($summary['daily'][6])->toBe(['date' => '2026-10-15', 'visits' => 60, 'leads' => 2])
            ->and($summary['daily'][4])->toBe(['date' => '2026-10-13', 'visits' => 40, 'leads' => 0])
            ->and($summary['daily'][0])->toBe(['date' => '2026-10-09', 'visits' => 0, 'leads' => 0])
            ->and($summary['totals'])->toBe(['visits' => 100, 'leads' => 2, 'conversion' => 2.0])
            ->and($summary['breakdown']['source']['items'][0])->toBe(['key' => 'direct', 'label' => 'Direto', 'count' => 50, 'percent' => 50.0])
            ->and($summary['breakdown']['source']['items'][1]['key'])->toBe('search')
            ->and($summary['breakdown']['cta']['items'][0])->toBe(['key' => 'hero', 'label' => 'Quero conhecer (topo)', 'count' => 25, 'percent' => 25.0])
            ->and($summary['breakdown']['device']['items'][0]['percent'])->toBe(0.0);
    });

    it('has no percentages or conversion before there are visits', function () {
        $summary = LandingMetrics::summary(30);

        expect($summary['totals'])->toBe(['visits' => 0, 'leads' => 0, 'conversion' => null])->and($summary['breakdown']['source']['items'][0]['percent'])->toBeNull();
    });

    it('prunes counters older than 400 days and keeps the rest', function () {
        $this->travelTo(Carbon::parse('2026-10-15 15:00:00', 'UTC'));
        DB::table('marketing_metrics')->insert([
            ['day' => '2025-01-01', 'metric' => 'visit', 'dimension' => '', 'total' => 5],
            ['day' => '2026-09-01', 'metric' => 'visit', 'dimension' => '', 'total' => 7],
        ]);

        Artisan::call('marketing:prune-metrics');

        expect(DB::table('marketing_metrics')->pluck('day')->all())->toBe(['2026-09-01']);
    });
});

describe('platform admin', function () {
    it('is hidden (404) from anyone who is not a platform admin', function () {
        $this->get('/plataforma/medicao')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/plataforma/medicao')->assertNotFound();
    });

    it('shows the summary, defaults to 30 days and accepts only the known periods', function () {
        $admin = metricAdmin();
        LandingMetrics::record([['metric' => 'visit', 'dimension' => '']]);

        $this->actingAs($admin)->get('/plataforma/medicao')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Platform/Metrics/Index')->where('enabled', true)->where('periods', [7, 30, 90])->where('summary.days', 30)->has('summary.daily', 30)->where('summary.totals.visits', 1));

        $this->actingAs($admin)->get('/plataforma/medicao?dias=7')->assertInertia(fn ($page) => $page->where('summary.days', 7)->has('summary.daily', 7));
        $this->actingAs($admin)->get('/plataforma/medicao?dias=9999')->assertInertia(fn ($page) => $page->where('summary.days', 30));

        config(['platform.landing.analytics' => false]);
        $this->actingAs($admin)->get('/plataforma/medicao')->assertInertia(fn ($page) => $page->where('enabled', false));
    });
});
