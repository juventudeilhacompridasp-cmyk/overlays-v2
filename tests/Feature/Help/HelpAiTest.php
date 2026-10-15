<?php

use App\Modules\Help\Models\HelpNote;
use App\Modules\Help\Models\HelpQuestion;
use App\Modules\Help\Support\HelpAnswerer;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    HelpLibrary::flush();
    CurrentOrganization::set(Organization::factory()->create([
        'slug' => config('platform.default_organization_slug'),
        'opt_in_features' => ['help_ai'],
        'settings' => ['help_ai' => ['enabled' => true]],
    ]));
    config(['services.anthropic.key' => 'chave-de-teste', 'services.anthropic.url' => 'https://api.anthropic.com/v1/messages']);
});

function fakeProvider(?string $answer, array $sources = ['lancar-sumula'], int $status = 200): void
{
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['answer' => $answer, 'sources' => $sources])]]], $status)]);
}

const AI_QUESTION = 'Como lançar um gol na súmula?';

describe('availability', function () {
    it('needs the on-demand feature, the organization switch and the provider key', function () {
        $this->actingAs(panelUser(Role::Admin));
        Http::fake();

        $this->get(route('panel.help.index'))->assertInertia(fn ($page) => $page->where('ai.ask', 'panel.help.ask')->where('ai.remaining', 10));

        CurrentOrganization::get()->update(['settings' => ['help_ai' => ['enabled' => false]]]);
        $this->get(route('panel.help.index'))->assertInertia(fn ($page) => $page->where('ai', null));
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertForbidden();

        CurrentOrganization::get()->update(['settings' => ['help_ai' => ['enabled' => true]]]);
        config(['services.anthropic.key' => null]);
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertForbidden();

        config(['services.anthropic.key' => 'x']);
        CurrentOrganization::get()->update(['opt_in_features' => null]);
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertForbidden();

        Http::assertNothingSent();
    });

    it('validates the question', function () {
        $this->actingAs(panelUser(Role::Admin))->postJson(route('panel.help.ask'), ['question' => 'oi'])->assertUnprocessable();
        $this->postJson(route('panel.help.ask'), ['question' => str_repeat('a', 301)])->assertUnprocessable();
    });
});

describe('answers', function () {
    it('answers from the matching articles with their sources, and keeps no text of an answered question', function () {
        fakeProvider('Abra o Mesário e lance o gol na súmula.');
        $this->actingAs(panelUser(Role::Admin));

        $response = $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertOk();

        expect($response->json('answer'))->toBe('Abra o Mesário e lance o gol na súmula.')
            ->and($response->json('sources.0.slug'))->toBe('lancar-sumula')
            ->and($response->json('sources.0.url'))->toBe(route('panel.help.show', 'lancar-sumula'))
            ->and($response->json('remaining'))->toBe(9);

        $row = HelpQuestion::query()->firstOrFail();
        expect($row->answered)->toBeTrue()->and($row->charged)->toBeTrue()->and($row->question)->toBeNull();
    });

    it('sends only the question (scrubbed) and public help excerpts to the provider, never organization notes', function () {
        fakeProvider('Resposta.');
        $admin = panelUser(Role::Admin);
        HelpNote::query()->create(['audience' => 'panel', 'title' => 'Súmula interna', 'body' => 'SEGREDO-DA-ORGANIZACAO sobre lançar gol na súmula']);
        $this->actingAs($admin)->postJson(route('panel.help.ask'), ['question' => 'Como lançar gol na súmula? Meu email é joao@exemplo.com e meu cpf 123.456.789-09'])->assertOk();

        Http::assertSent(function ($request) use ($admin) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'Lançar a súmula de um jogo')
                && ! str_contains($body, 'SEGREDO-DA-ORGANIZACAO')
                && ! str_contains($body, 'joao@exemplo.com')
                && ! str_contains($body, '123.456.789-09')
                && ! str_contains($body, $admin->email)
                && str_contains($body, '[removido]')
                && $request->hasHeader('x-api-key', 'chave-de-teste');
        });
    });

    it('does not call the provider without a related article, and records the gap without charging', function () {
        Http::fake();
        $this->actingAs(panelUser(Role::Admin));

        $response = $this->postJson(route('panel.help.ask'), ['question' => 'Quantos planetas orbitam Betelgeuse?'])->assertOk();

        expect($response->json('answer'))->toBeNull()->and($response->json('message'))->toContain('Não encontrei')->and($response->json('remaining'))->toBe(10);
        Http::assertNothingSent();

        $row = HelpQuestion::query()->firstOrFail();
        expect($row->answered)->toBeFalse()->and($row->charged)->toBeFalse()->and($row->question)->toBe('Quantos planetas orbitam Betelgeuse?');
    });

    it('treats an answer with no known source as no answer (possible invention) and charges the attempt', function () {
        fakeProvider('Clique no botão mágico.', ['artigo-que-nao-existe']);
        $this->actingAs(panelUser(Role::Admin));

        $response = $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertOk();

        expect($response->json('answer'))->toBeNull()->and($response->json('sources'))->toBe([]);
        $row = HelpQuestion::query()->firstOrFail();
        expect($row->charged)->toBeTrue()->and($row->answered)->toBeFalse()->and($row->question)->not->toBeNull();
    });

    it('strips markup from the answer and reports provider failures, still counting the attempt', function () {
        fakeProvider('<b>Abra</b> a súmula.');
        $this->actingAs(panelUser(Role::Admin));
        expect($this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->json('answer'))->toBe('Abra a súmula.');

        Http::fake(['api.anthropic.com/*' => Http::response('erro', 500)]);
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertStatus(502);

        expect(HelpQuestion::query()->where('charged', true)->count())->toBe(2);
    });
});

describe('limits', function () {
    it('stops a person at the daily limit and the organization at the monthly one', function () {
        fakeProvider('Resposta.');
        config(['platform.help_ai.user_daily' => 2, 'platform.help_ai.monthly' => 3]);
        $first = panelUser(Role::Admin);

        $this->actingAs($first)->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertOk();
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertOk();
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertStatus(429);

        $second = panelUser(Role::Organizer);
        $this->actingAs($second)->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertOk();
        $this->postJson(route('panel.help.ask'), ['question' => AI_QUESTION])->assertStatus(429)->assertJsonPath('remaining', 0);
    });
});

describe('gaps and parameters', function () {
    it('shows the unanswered questions to the administrator only when the feature is released', function () {
        $admin = panelUser(Role::Admin);
        HelpQuestion::query()->create(['user_id' => $admin->getKey(), 'charged' => false, 'answered' => false, 'question' => 'Como exportar para o Excel?']);
        HelpQuestion::query()->create(['user_id' => $admin->getKey(), 'charged' => true, 'answered' => true, 'question' => null]);

        $this->actingAs($admin)->get(route('panel.help-notes.index'))->assertInertia(fn ($page) => $page->has('gaps', 1)->where('gaps.0.question', 'Como exportar para o Excel?'));

        CurrentOrganization::get()->update(['opt_in_features' => null]);
        $this->get(route('panel.help-notes.index'))->assertInertia(fn ($page) => $page->where('gaps', null));
    });

    it('lets the administrator turn it on in the parameters only while the feature is released', function () {
        CurrentOrganization::get()->update(['settings' => []]);
        $payload = fn (bool $on) => ['disabled' => [], 'mesario' => ['days_ahead' => 2, 'finished_hours' => 6, 'limit' => 40], 'portal' => ['home' => 8, 'widgets' => 6, 'scorers' => 10, 'stats_top' => 5, 'stats_form' => 5], 'public_api' => true, 'quick_result' => true, 'help_ai' => $on];

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.parameters.edit'))->assertInertia(fn ($page) => $page->where('help_ai', ['released' => true, 'enabled' => false, 'configured' => true]));

        $this->patch(route('panel.parameters.update'), $payload(true))->assertSessionHas('success');
        expect(CurrentOrganization::get()->fresh()->settings['help_ai']['enabled'])->toBeTrue();

        CurrentOrganization::get()->update(['opt_in_features' => null]);
        $this->patch(route('panel.parameters.update'), $payload(false))->assertSessionHas('success');
        expect(CurrentOrganization::get()->fresh()->settings['help_ai']['enabled'])->toBeTrue();
    });
});

it('scrubs e-mails and long digit sequences', function () {
    expect(HelpAnswerer::scrub('fale com a@b.com ou 11 98765-4321 e doc 123.456.789-09 sobre o jogo 3'))->toBe('fale com [removido] ou [removido] e doc [removido] sobre o jogo 3');
});
