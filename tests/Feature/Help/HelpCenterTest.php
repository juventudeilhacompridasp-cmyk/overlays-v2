<?php

use App\Models\User;
use App\Modules\Help\Support\HelpAccess;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Ability;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    HelpLibrary::flush();
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function helpPlatformAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

function helpSlugs($page): array
{
    return collect($page->toArray()['props']['categories'] ?? [])->flatMap(fn ($category) => collect($category['articles'])->pluck('slug'))->all();
}

describe('the articles of the repository', function () {
    it('are well formed: unique slugs, known abilities, features and screens', function () {
        $articles = HelpLibrary::all();
        $slugs = array_map(fn ($article) => $article->slug, $articles);

        expect($articles)->not->toBeEmpty()
            ->and($slugs)->toBe(array_values(array_unique($slugs)));

        $abilities = array_map(fn (Ability $ability) => $ability->value, Ability::cases());
        $features = array_map(fn (Feature $feature) => $feature->value, Feature::cases());

        foreach ($articles as $article) {
            expect($article->title)->not->toBe('', "título de {$article->slug}")
                ->and($article->body)->not->toBe('', "corpo de {$article->slug}")
                ->and($article->tags)->not->toBeEmpty("tags de {$article->slug}")
                ->and(str_contains($article->body, '<'))->toBeFalse("HTML cru em {$article->slug}");

            if ($article->ability !== null) {
                expect($abilities)->toContain($article->ability);
            }

            if ($article->feature !== null) {
                expect($features)->toContain($article->feature);
            }

            if ($article->route !== null) {
                expect(Route::has($article->route))->toBeTrue("rota {$article->route} de {$article->slug}");
            }
        }
    });

    it('only link to articles and screens that exist', function () {
        $slugs = array_map(fn ($article) => $article->slug, HelpLibrary::all());

        foreach (HelpLibrary::all() as $article) {
            preg_match_all('/\]\((help|route):([^)]+)\)/', $article->body, $links, PREG_SET_ORDER);

            foreach ($links as [, $kind, $target]) {
                if ($kind === 'help') {
                    expect(in_array($target, $slugs, true))->toBeTrue("link help:{$target} em {$article->slug}");
                } else {
                    expect(Route::has($target))->toBeTrue("link route:{$target} em {$article->slug}");
                    expect(fn () => route($target))->not->toThrow(Exception::class);
                }
            }
        }
    });
});

describe('who sees what', function () {
    it('shows the panel articles to any member, but hides the ones that need a permission', function () {
        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.help.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Help/Index'));

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.help.index'))->assertInertia(function ($page) {
            $slugs = helpSlugs($page);
            expect($slugs)->toContain('primeiros-passos')
                ->and($slugs)->not->toContain('comunicacoes')
                ->and($slugs)->not->toContain('lancar-sumula')
                ->and($slugs)->not->toContain('escalar-sua-equipe')
                ->and($slugs)->not->toContain('liberar-recursos');
        });

        $this->actingAs(panelUser(Role::Operator))->get(route('panel.help.index'))->assertInertia(function ($page) {
            expect(helpSlugs($page))->toContain('lancar-sumula')->and(helpSlugs($page))->not->toContain('comunicacoes');
        });

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.index'))->assertInertia(function ($page) {
            expect(helpSlugs($page))->toContain('comunicacoes', 'usuarios-e-perfis', 'criar-competicao');
        });
    });

    it('follows the access profile, not only the role', function () {
        $user = panelUser(Role::Viewer);
        $profile = AccessProfile::query()->create(['name' => 'Só jogos', 'slug' => 'so-jogos', 'is_system' => false]);
        $profile->grants()->create(['permission' => 'games.operate']);
        Membership::query()->where('user_id', $user->getKey())->update(['access_profile_id' => $profile->getKey()]);

        $this->actingAs($user->fresh())->get(route('panel.help.index'))->assertInertia(function ($page) {
            expect(helpSlugs($page))->toContain('lancar-sumula')->and(helpSlugs($page))->not->toContain('criar-competicao');
        });
    });

    it('hides help about features the organization does not have', function () {
        CurrentOrganization::get()->update(['plan' => 'pro', 'features' => ['reports']]);
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get(route('panel.help.index'))->assertInertia(function ($page) {
            expect(helpSlugs($page))->not->toContain('transmissao-obs')
                ->and(helpSlugs($page))->not->toContain('inscricao-online')
                ->and(helpSlugs($page))->not->toContain('sumulas-enviadas')
                ->and(helpSlugs($page))->toContain('primeiros-passos');
        });

        $this->actingAs($admin)->get(route('panel.help.show', 'transmissao-obs'))->assertNotFound();

        CurrentOrganization::get()->update(['features' => ['overlays']]);
        $this->actingAs(User::query()->findOrFail($admin->getKey()))->get(route('panel.help.show', 'transmissao-obs'))->assertOk();
    });

    it('shows the manager only manager articles, and the panel never shows them', function () {
        $manager = managerFor(Team::factory()->create());

        $this->actingAs($manager)->get(route('manager.help.index'))->assertOk()->assertInertia(function ($page) {
            expect(helpSlugs($page))->toContain('escalar-sua-equipe', 'primeiros-passos-do-gestor')
                ->and(helpSlugs($page))->not->toContain('primeiros-passos')
                ->and(helpSlugs($page))->not->toContain('liberar-recursos');
        });

        $this->actingAs($manager)->get(route('manager.help.show', 'comunicacoes'))->assertNotFound();
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.show', 'escalar-sua-equipe'))->assertNotFound();
        $this->actingAs(panelUser(Role::Admin))->get(route('manager.help.index'))->assertForbidden();
    });

    it('keeps the platform articles for the platform administrator', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('platform.help.index'))->assertNotFound();
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.show', 'liberar-recursos'))->assertNotFound();

        $this->actingAs(helpPlatformAdmin())->get(route('platform.help.index'))->assertOk()->assertInertia(function ($page) {
            expect(helpSlugs($page))->toContain('liberar-recursos', 'leitura-de-sumulas-na-plataforma')->and(helpSlugs($page))->not->toContain('primeiros-passos');
        });

        $both = helpPlatformAdmin();
        Membership::query()->create(['user_id' => $both->getKey(), 'role' => Role::Admin]);
        $this->actingAs($both)->get(route('panel.help.show', 'liberar-recursos'))->assertOk();
    });

    it('needs login', function () {
        $this->get(route('panel.help.index'))->assertRedirect();
    });

    it('applies the same rule through HelpAccess', function () {
        $article = HelpLibrary::find('comunicacoes');

        expect(HelpAccess::canSee($article, panelUser(Role::Admin), 'panel'))->toBeTrue()
            ->and(HelpAccess::canSee($article, panelUser(Role::Organizer), 'panel'))->toBeFalse()
            ->and(HelpAccess::canSee($article, null, 'panel'))->toBeFalse();
    });
});

describe('searching and reading', function () {
    it('finds articles by what the person types', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get(route('panel.help.index', ['q' => 'convidar gestor']))->assertInertia(function ($page) {
            $results = $page->toArray()['props']['results'];
            expect($results[0]['slug'])->toBe('convidar-gestor')->and($results[0]['snippet'])->not->toBe('');
        });

        $this->actingAs($admin)->get(route('panel.help.index', ['q' => 'avisos e-mail desligar']))->assertInertia(function ($page) {
            expect(collect($page->toArray()['props']['results'])->pluck('slug')->all())->toContain('comunicacoes');
        });

        $this->actingAs($admin)->get(route('panel.help.index', ['q' => 'zzzzzz']))->assertInertia(fn ($page) => $page->where('results', []));
    });

    it('does not search inside articles the person cannot see', function () {
        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.help.index', ['q' => 'comunicações avisos']))->assertInertia(function ($page) {
            expect(collect($page->toArray()['props']['results'])->pluck('slug')->all())->not->toContain('comunicacoes');
        });
    });

    it('renders an article with resolved links and the related ones', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get(route('panel.help.show', 'primeiros-passos'))->assertOk()->assertInertia(function ($page) {
            $article = $page->toArray()['props']['article'];
            $links = collect($article['blocks'])->flatMap(fn ($block) => collect($block['inline'] ?? [])->merge(collect($block['items'] ?? [])->flatten(1)))->where('t', 'link');

            expect($article['title'])->toBe('Primeiros passos no painel')
                ->and($article['screen_url'])->toContain('/painel')
                ->and($links->pluck('href')->all())->toContain(route('panel.users.index'), route('panel.help.show', 'usuarios-e-perfis'));
        });
    });

    it('shows a link to a hidden article as plain text', function () {
        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.help.show', 'primeiros-passos'))->assertOk()->assertInertia(function ($page) {
            $hrefs = collect($page->toArray()['props']['article']['blocks'])->flatMap(fn ($block) => collect($block['inline'] ?? [])->merge(collect($block['items'] ?? [])->flatten(1)))->where('t', 'link')->pluck('href')->all();

            expect($hrefs)->not->toContain(route('panel.help.show', 'usuarios-e-perfis'));
        });
    });

    it('answers 404 for an unknown article', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.show', 'nao-existe'))->assertNotFound();
    });
});
