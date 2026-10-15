<?php

use App\Models\User;
use App\Modules\Status\Actions\ManageIncidents;
use App\Modules\Status\Actions\RunProbes;
use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use App\Modules\Status\Mail\IncidentNotice;
use App\Modules\Status\Mail\SubscriptionConfirm;
use App\Modules\Status\Models\Component;
use App\Modules\Status\Models\Subscriber;
use App\Modules\Status\Probes\Probe;
use App\Modules\Status\Probes\ProbeResult;
use App\Modules\Status\Support\StatusBadge;
use App\Modules\Status\Support\StatusComponents;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Sonda de mentira desta suíte: `$ok` decide o resultado da chave `web`. */
class SubscriptionFakeProbe implements Probe
{
    public static bool $ok = true;

    public function key(): string
    {
        return 'web';
    }

    public function name(): string
    {
        return 'web';
    }

    public function run(): ProbeResult
    {
        return self::$ok ? ProbeResult::ok(10) : ProbeResult::fail('falhou');
    }
}

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    SubscriptionFakeProbe::$ok = true;
    Cache::flush();
    Mail::fake();
    config(['platform.status.storage_disk' => '', 'platform.status.publish_disk' => null, 'platform.status.notify' => true]);
});

function confirmedSubscriber(string $email = 'ana@exemplo.test'): Subscriber
{
    return Subscriber::query()->create(['email' => $email, 'token' => Str::random(40), 'confirmed_at' => now()]);
}

function pendingSubscriber(string $email = 'bia@exemplo.test'): Subscriber
{
    return Subscriber::query()->create(['email' => $email, 'token' => Str::random(40)]);
}

function webId(): string
{
    return StatusComponents::ensure()->firstWhere('key', 'web')->getKey();
}

describe('subscribing', function () {
    it('creates a pending subscription and asks for confirmation, without revealing anything else', function () {
        $this->post('/status/inscrever', ['email' => 'Ana@Exemplo.Test', 'website' => ''])->assertRedirect(route('status.index'))->assertSessionHas('success');

        $subscriber = Subscriber::query()->firstOrFail();
        expect($subscriber->email)->toBe('ana@exemplo.test')->and($subscriber->isConfirmed())->toBeFalse()->and(strlen($subscriber->token))->toBe(40);
        Mail::assertSent(SubscriptionConfirm::class, fn (SubscriptionConfirm $mail) => $mail->hasTo('ana@exemplo.test') && str_contains($mail->link, $subscriber->token));
    });

    it('answers the same way for an address that is already confirmed and sends nothing', function () {
        confirmedSubscriber('ana@exemplo.test');

        $this->post('/status/inscrever', ['email' => 'ana@exemplo.test'])->assertRedirect(route('status.index'))->assertSessionHas('success');

        expect(Subscriber::query()->count())->toBe(1);
        Mail::assertNothingSent();
    });

    it('does not resend the confirmation more than once every few minutes', function () {
        $this->post('/status/inscrever', ['email' => 'ana@exemplo.test']);
        $this->post('/status/inscrever', ['email' => 'ana@exemplo.test']);

        Mail::assertSent(SubscriptionConfirm::class, 1);

        $this->travel(6)->minutes();
        $this->post('/status/inscrever', ['email' => 'ana@exemplo.test']);

        Mail::assertSent(SubscriptionConfirm::class, 2);
        expect(Subscriber::query()->count())->toBe(1);
    });

    it('validates the e-mail and drops submissions that fill the honeypot', function () {
        $this->post('/status/inscrever', ['email' => 'nao-e-email'])->assertSessionHasErrors('email');
        $this->post('/status/inscrever', ['email' => 'ana@exemplo.test', 'website' => 'http://spam.test'])->assertSessionHasErrors('website');

        expect(Subscriber::query()->count())->toBe(0);
        Mail::assertNothingSent();
    });

    it('is rate limited', function () {
        foreach (range(1, 5) as $index) {
            $this->post('/status/inscrever', ['email' => "pessoa{$index}@exemplo.test"])->assertSessionHasNoErrors();
        }

        $this->post('/status/inscrever', ['email' => 'pessoa6@exemplo.test'])->assertStatus(429);
    });
});

describe('confirming and cancelling', function () {
    it('confirms with the token, accepts a second click and rejects unknown tokens', function () {
        $subscriber = pendingSubscriber();

        $this->get("/status/confirmar/{$subscriber->token}")->assertOk()->assertInertia(fn ($page) => $page->component('Status/Notice')->where('title', 'Inscrição confirmada'));
        expect($subscriber->fresh()->isConfirmed())->toBeTrue();

        $this->get("/status/confirmar/{$subscriber->token}")->assertInertia(fn ($page) => $page->where('title', 'Inscrição confirmada'));
        $this->get('/status/confirmar/'.str_repeat('a', 40))->assertInertia(fn ($page) => $page->where('title', 'Link inválido ou expirado'));
        $this->get('/status/confirmar/curto')->assertNotFound();
    });

    it('does not confirm a request older than the pending period', function () {
        $subscriber = pendingSubscriber();
        Subscriber::query()->whereKey($subscriber->getKey())->update(['created_at' => now()->subDays(8)]);

        $this->get("/status/confirmar/{$subscriber->token}")->assertInertia(fn ($page) => $page->where('title', 'Link inválido ou expirado'));

        expect($subscriber->fresh()->isConfirmed())->toBeFalse();
    });

    it('cancels in two steps so that opening the link in a mail reader does not cancel', function () {
        $subscriber = confirmedSubscriber();

        $this->get("/status/cancelar/{$subscriber->token}")->assertOk()->assertInertia(fn ($page) => $page->where('title', 'Cancelar avisos de status')->where('action.label', 'Cancelar avisos'));
        expect(Subscriber::query()->count())->toBe(1);

        $this->post("/status/cancelar/{$subscriber->token}")->assertOk()->assertInertia(fn ($page) => $page->where('title', 'Avisos cancelados'));
        expect(Subscriber::query()->count())->toBe(0);

        $this->post("/status/cancelar/{$subscriber->token}")->assertOk();
        $this->get("/status/cancelar/{$subscriber->token}")->assertInertia(fn ($page) => $page->where('title', 'Inscrição não encontrada'));
    });

    it('prunes requests that were never confirmed and keeps the confirmed ones', function () {
        $old = pendingSubscriber('velha@exemplo.test');
        Subscriber::query()->whereKey($old->getKey())->update(['created_at' => now()->subDays(8)]);
        pendingSubscriber('nova@exemplo.test');
        confirmedSubscriber('ok@exemplo.test');
        Subscriber::query()->where('email', 'ok@exemplo.test')->update(['created_at' => now()->subDays(30)]);

        Artisan::call('status:prune');

        expect(Subscriber::query()->pluck('email')->sort()->values()->all())->toBe(['nova@exemplo.test', 'ok@exemplo.test']);
    });
});

describe('incident notices', function () {
    it('warns only the confirmed subscribers when an incident is opened and updated', function () {
        confirmedSubscriber('ana@exemplo.test');
        pendingSubscriber('bia@exemplo.test');

        $incident = app(ManageIncidents::class)->open('Lentidão no painel', Impact::Minor, IncidentState::Investigating, [webId()], 'Estamos investigando.');

        Mail::assertQueued(IncidentNotice::class, 1);
        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->hasTo('ana@exemplo.test') && $mail->title === 'Lentidão no painel' && $mail->stateLabel === 'Investigando' && str_contains($mail->cancelLink, '/status/cancelar/'));
        Mail::assertNotQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->hasTo('bia@exemplo.test'));

        app(ManageIncidents::class)->update($incident, IncidentState::Resolved, 'Normalizado.');

        Mail::assertQueued(IncidentNotice::class, 2);
        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->stateLabel === 'Resolvido' && $mail->body === 'Normalizado.');
    });

    it('warns about scheduled maintenance too', function () {
        confirmedSubscriber();

        app(ManageIncidents::class)->open('Atualização do Reverb', Impact::Maintenance, IncidentState::Scheduled, [webId()], 'Breve pausa.', now()->addDay(), now()->addDay()->addHour());

        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->stateLabel === 'Agendada');
    });

    it('warns when the probes open and resolve an automatic incident', function () {
        confirmedSubscriber();
        SubscriptionFakeProbe::$ok = false;

        foreach (range(1, 3) as $_) {
            (new RunProbes)->handle([new SubscriptionFakeProbe]);
        }

        Mail::assertQueued(IncidentNotice::class, 1);

        SubscriptionFakeProbe::$ok = true;

        foreach (range(1, 2) as $_) {
            (new RunProbes)->handle([new SubscriptionFakeProbe]);
        }

        Mail::assertQueued(IncidentNotice::class, 2);
        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->stateLabel === 'Resolvido');
    });

    it('sends nothing when notices are turned off, and never blocks the incident', function () {
        config(['platform.status.notify' => false]);
        confirmedSubscriber();

        $incident = app(ManageIncidents::class)->open('Teste', Impact::Minor, IncidentState::Investigating, [webId()], 'x');

        expect($incident->exists)->toBeTrue();
        Mail::assertNothingQueued();
    });
});

describe('per-component notices', function () {
    it('warns only who follows a component the incident touches, and everyone when nothing was chosen', function () {
        confirmedSubscriber('todos@exemplo.test');
        $web = confirmedSubscriber('web@exemplo.test');
        $web->update(['components' => ['web']]);
        $database = confirmedSubscriber('dados@exemplo.test');
        $database->update(['components' => ['database']]);

        app(ManageIncidents::class)->open('Lentidão no painel', Impact::Minor, IncidentState::Investigating, [webId()], 'Estamos investigando.');

        Mail::assertQueued(IncidentNotice::class, 2);
        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->hasTo('todos@exemplo.test'));
        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->hasTo('web@exemplo.test'));
        Mail::assertNotQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->hasTo('dados@exemplo.test'));
    });

    it('still warns everyone about an incident with no component, and carries the preferences link in every notice', function () {
        $database = confirmedSubscriber('dados@exemplo.test');
        $database->update(['components' => ['database']]);

        app(ManageIncidents::class)->open('Manutenção geral', Impact::Maintenance, IncidentState::Scheduled, [], 'Janela de manutenção.', now()->addDay(), now()->addDay()->addHour());

        Mail::assertQueued(IncidentNotice::class, fn (IncidentNotice $mail) => $mail->hasTo('dados@exemplo.test') && str_contains((string) $mail->preferencesLink, "/status/preferencias/{$database->token}"));
    });

    it('lets a confirmed subscriber pick components with the token, and choosing all goes back to the default', function () {
        $subscriber = confirmedSubscriber();
        StatusComponents::ensure();

        $this->get("/status/preferencias/{$subscriber->token}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Status/Preferences')
            ->where('components', fn ($list) => count($list) >= 2 && collect($list)->every(fn ($item) => $item['selected'] === true)));

        $this->post("/status/preferencias/{$subscriber->token}", ['components' => ['web']])->assertRedirect(route('status.preferences.show', $subscriber->token))->assertSessionHas('success');
        expect($subscriber->fresh()->components)->toBe(['web']);

        $this->get("/status/preferencias/{$subscriber->token}")->assertInertia(fn ($page) => $page->where('components', fn ($list) => collect($list)->firstWhere('key', 'web')['selected'] === true && collect($list)->firstWhere('key', 'database')['selected'] === false));

        $all = Component::query()->where('visible', true)->pluck('key')->all();
        $this->post("/status/preferencias/{$subscriber->token}", ['components' => $all])->assertSessionHas('success');
        expect($subscriber->fresh()->components)->toBeNull();
    });

    it('refuses an empty choice, unknown keys, pending subscribers and unknown tokens', function () {
        $subscriber = confirmedSubscriber();
        StatusComponents::ensure();

        $this->post("/status/preferencias/{$subscriber->token}", ['components' => []])->assertSessionHasErrors('components');
        $this->post("/status/preferencias/{$subscriber->token}", ['components' => ['nao-existe']])->assertNotFound();
        expect($subscriber->fresh()->components)->toBeNull();

        $pending = pendingSubscriber();
        $this->get("/status/preferencias/{$pending->token}")->assertOk()->assertInertia(fn ($page) => $page->component('Status/Notice')->where('title', 'Inscrição não encontrada'));
        $this->post("/status/preferencias/{$pending->token}", ['components' => ['web']])->assertNotFound();

        $this->get('/status/preferencias/'.str_repeat('a', 40))->assertInertia(fn ($page) => $page->component('Status/Notice'));
    });

    it('keeps the preferences page out of the search engines', function () {
        expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /status/preferencias');
    });
});

describe('badge', function () {
    it('serves a valid SVG for any origin that follows the current status', function () {
        $response = $this->get('/status/selo.svg')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');

        expect($response->headers->get('Content-Type'))->toContain('image/svg+xml')->and($response->getContent())->toContain('operacional')->toContain('#059669');
        expect(simplexml_load_string($response->getContent()))->not->toBeFalse();

        Cache::flush();
        app(ManageIncidents::class)->open('Fora do ar', Impact::Major, IncidentState::Investigating, [webId()], 'x');

        $down = $this->get('/status/selo.svg')->getContent();
        expect($down)->toContain('fora do ar')->toContain('#dc2626');
    });

    it('falls back to operational for an unknown state and always escapes its text', function () {
        expect(StatusBadge::svg('qualquer-coisa'))->toContain('operacional');

        foreach (['operational', 'degraded', 'outage', 'maintenance'] as $overall) {
            expect(simplexml_load_string(StatusBadge::svg($overall)))->not->toBeFalse();
        }
    });
});

describe('platform admin', function () {
    it('shows the subscriber counts and the badge snippet only to platform admins', function () {
        confirmedSubscriber('a@exemplo.test');
        confirmedSubscriber('b@exemplo.test');
        pendingSubscriber('c@exemplo.test');
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)->get('/plataforma/status')->assertOk()->assertInertia(fn ($page) => $page->where('subscribers', ['confirmed' => 2, 'pending' => 1])->where('badge_url', route('status.badge')));
        $this->actingAs(User::factory()->create())->get('/plataforma/status')->assertNotFound();
    });
});
