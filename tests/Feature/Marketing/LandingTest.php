<?php

use App\Models\User;
use App\Modules\Marketing\Mail\LeadReceived;
use App\Modules\Marketing\Models\Lead;
use App\Modules\Marketing\Support\LandingContent;
use App\Modules\Marketing\Support\LandingHosts;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationResolver;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug'), 'name' => 'Organização Secreta']));
    config(['platform.contact_email' => null, 'platform.landing.root' => false, 'platform.landing.hosts' => null]);
});

/** @return array<string, mixed> */
function leadData(array $over = []): array
{
    return array_merge([
        'name' => 'Maria Souza',
        'email' => 'Maria@Liga.Test',
        'organization_name' => 'Liga da Ilha',
        'interest' => 'both',
        'message' => 'Queremos transmitir o campeonato municipal com overlays.',
        'consent' => true,
        'website' => '',
    ], $over);
}

function landingAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

describe('landing page', function () {
    it('is public and never shows organization data', function () {
        $this->get('/produto')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Marketing/Landing')->has('content.plans', 3)->where('seo.json_ld.@type', 'SoftwareApplication')->where('seo.url', route('marketing.landing')));

        expect(json_encode(LandingContent::build()))->not->toContain('Organização Secreta');
    });

    it('builds packages, features and sports from the code, never by hand', function () {
        $content = LandingContent::build();
        $labels = array_column($content['matrix'], 'label');

        foreach (Feature::cases() as $feature) {
            expect($labels)->toContain($feature->label());
        }

        $market = collect($content['matrix'])->firstWhere('label', Feature::Market->label());
        $arts = collect($content['matrix'])->firstWhere('label', Feature::Arts->label());

        expect($market['on_demand'])->toBeTrue()->and(array_filter($market['plans']))->toBe([])
            ->and($arts['plans'])->toBe(['basic' => false, 'standard' => true, 'pro' => true])
            ->and(array_column($content['plans'], 'key'))->toBe(array_map(fn (Plan $plan) => $plan->value, Plan::cases()))
            ->and($content['sports'])->toContain('Futebol')->toContain('Basquete');
    });

    it('is listed in the sitemap', function () {
        $this->get('/sitemap.xml')->assertSee(route('marketing.landing'), false);
    });

    it('only takes over the root of the landing hosts when turned on', function () {
        expect(LandingHosts::all())->toBe([]);

        config(['platform.landing.root' => true, 'platform.base_domain' => 'Exemplo.test']);
        expect(LandingHosts::all())->toBe(['exemplo.test', 'www.exemplo.test']);

        config(['platform.landing.hosts' => ' Produto.Exemplo.test , www.exemplo.test ,']);
        expect(LandingHosts::all())->toBe(['produto.exemplo.test', 'www.exemplo.test']);
    });

    it('treats www.{base domain} as the platform host, not as an organization', function () {
        config(['platform.base_domain' => 'exemplo.test']);

        expect(OrganizationResolver::forHost('www.exemplo.test')->slug)->toBe(config('platform.default_organization_slug'));
    });
});

describe('gallery', function () {
    it('lists the same screenshots the generator produces, and every file exists as a light WebP', function () {
        $config = (string) file_get_contents(base_path('scripts/marketing/shots.config.mjs'));
        preg_match_all("/name: '([a-z0-9-]+)'/", $config, $matches);
        $generated = $matches[1];
        $listed = array_map(fn (array $item) => basename($item['src'], '.webp'), LandingContent::gallery());

        expect($generated)->not->toBeEmpty()->and($listed)->toBe($generated);

        foreach (LandingContent::gallery() as $item) {
            $path = public_path(ltrim($item['src'], '/'));
            $bytes = (string) file_get_contents($path);

            expect(file_exists($path))->toBeTrue("falta {$item['src']}")
                ->and(substr($bytes, 0, 4).substr($bytes, 8, 4))->toBe('RIFFWEBP')
                ->and(strlen($bytes))->toBeLessThan(300 * 1024)
                ->and($item['alt'])->toContain('dados fictícios');
        }
    });

    it('reaches the page and has no file without a listing', function () {
        $this->get('/produto')->assertOk()->assertInertia(fn ($page) => $page->has('content.gallery', count(LandingContent::gallery())));

        $files = array_map(fn (string $path) => '/marketing/'.basename($path), glob(public_path('marketing/*.webp')) ?: []);
        sort($files);
        $listed = array_column(LandingContent::gallery(), 'src');
        sort($listed);

        expect($files)->toBe($listed);
    });
});

describe('demo video', function () {
    it('points to an existing light WebM with a poster from the gallery', function () {
        $video = LandingContent::video();
        $bytes = (string) file_get_contents(public_path(ltrim($video['src'], '/')));

        expect(substr($bytes, 0, 4))->toBe("\x1A\x45\xDF\xA3")
            ->and(strlen($bytes))->toBeLessThan(2 * 1024 * 1024)
            ->and(array_column(LandingContent::gallery(), 'src'))->toContain($video['poster'])
            ->and($video['text'])->toContain('fictícios');

        $this->get('/produto')->assertOk()->assertInertia(fn ($page) => $page->where('content.video.src', $video['src']));
    });
});

describe('contact form', function () {
    it('stores the lead with consent, normalizes the e-mail and warns the team', function () {
        Mail::fake();
        config(['platform.contact_email' => 'contato@plataforma.test']);

        $this->post('/produto/contato', leadData())->assertRedirect(route('marketing.landing').'#contato')->assertSessionHas('success');

        $lead = Lead::query()->firstOrFail();
        expect($lead->email)->toBe('maria@liga.test')->and($lead->status)->toBe('new')->and($lead->consent_at)->not->toBeNull()->and($lead->organization_name)->toBe('Liga da Ilha');
        Mail::assertSent(LeadReceived::class, fn (LeadReceived $mail) => $mail->hasTo('contato@plataforma.test') && $mail->lead->is($lead));
    });

    it('keeps the lead and sends nothing when no contact e-mail is configured', function () {
        Mail::fake();

        $this->post('/produto/contato', leadData())->assertSessionHasNoErrors();

        expect(Lead::query()->count())->toBe(1);
        Mail::assertNothingSent();
    });

    it('requires the privacy consent and a real message', function () {
        $this->post('/produto/contato', leadData(['consent' => false]))->assertSessionHasErrors('consent');
        $this->post('/produto/contato', leadData(['message' => 'oi']))->assertSessionHasErrors('message');
        $this->post('/produto/contato', leadData(['email' => 'não-é-email']))->assertSessionHasErrors('email');
        $this->post('/produto/contato', leadData(['interest' => 'hackear']))->assertSessionHasErrors('interest');

        expect(Lead::query()->count())->toBe(0);
    });

    it('drops submissions that fill the honeypot', function () {
        $this->post('/produto/contato', leadData(['website' => 'http://spam.test']))->assertSessionHasErrors('website');

        expect(Lead::query()->count())->toBe(0);
    });

    it('is rate limited', function () {
        foreach (range(1, 5) as $_) {
            $this->post('/produto/contato', leadData())->assertSessionHasNoErrors();
        }

        $this->post('/produto/contato', leadData())->assertStatus(429);
    });
});

describe('platform admin', function () {
    it('hides the contacts from anyone who is not a platform admin', function () {
        $lead = Lead::query()->create(['name' => 'A', 'email' => 'a@a.test', 'message' => 'mensagem longa o bastante', 'status' => 'new', 'consent_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/plataforma/contatos')->assertNotFound();
        $this->actingAs(User::factory()->create())->patch("/plataforma/contatos/{$lead->getKey()}", ['status' => 'archived'])->assertNotFound();
        $this->actingAs(User::factory()->create())->delete("/plataforma/contatos/{$lead->getKey()}")->assertNotFound();
    });

    it('lists, filters, updates and removes contacts', function () {
        $this->post('/produto/contato', leadData(['name' => 'Primeiro']));
        $this->post('/produto/contato', leadData(['name' => 'Segundo']));
        $first = Lead::query()->where('name', 'Primeiro')->firstOrFail();
        $admin = landingAdmin();

        $this->actingAs($admin)->get('/plataforma/contatos')->assertOk()->assertInertia(fn ($page) => $page->component('Platform/Leads/Index')->has('leads', 2)->where('counts.new', 2));

        $this->actingAs($admin)->patch("/plataforma/contatos/{$first->getKey()}", ['status' => 'contacted'])->assertRedirect();
        expect($first->fresh()->status)->toBe('contacted')->and($first->fresh()->handled_at)->not->toBeNull();

        $this->actingAs($admin)->get('/plataforma/contatos?status=contacted')->assertInertia(fn ($page) => $page->has('leads', 1)->where('leads.0.name', 'Primeiro')->where('filter', 'contacted'));
        $this->actingAs($admin)->patch("/plataforma/contatos/{$first->getKey()}", ['status' => 'invalido'])->assertSessionHasErrors('status');

        $this->actingAs($admin)->patch("/plataforma/contatos/{$first->getKey()}", ['status' => 'new']);
        expect($first->fresh()->handled_at)->toBeNull();

        $this->actingAs($admin)->delete("/plataforma/contatos/{$first->getKey()}")->assertRedirect();
        expect(Lead::query()->count())->toBe(1);
    });
});
