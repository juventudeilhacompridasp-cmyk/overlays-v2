<?php

use App\Models\User;
use App\Modules\Help\Models\HelpFeedback;
use App\Modules\Help\Support\ContextualHelp;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;

beforeEach(function () {
    HelpLibrary::flush();
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function contextPlatformAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

describe('which articles belong to a screen', function () {
    it('matches the exact route, or any route of the same resource, and never a one-level route by prefix', function () {
        expect(ContextualHelp::covers('panel.communications.edit', 'panel.communications.edit'))->toBeTrue()
            ->and(ContextualHelp::covers('panel.communications.edit', 'panel.communications.update'))->toBeTrue()
            ->and(ContextualHelp::covers('panel.communications.edit', 'panel.parameters.edit'))->toBeFalse()
            ->and(ContextualHelp::covers('panel.mesario', 'panel.mesario'))->toBeTrue()
            ->and(ContextualHelp::covers('panel.mesario', 'panel.games.sheet'))->toBeFalse()
            ->and(ContextualHelp::covers('mesario', 'panel.mesario'))->toBeFalse();
    });
});

describe('the help button of a screen', function () {
    it('shares the article linked to the current screen', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.communications.edit'))->assertInertia(fn ($page) => $page
            ->where('help.area', 'panel')
            ->where('help.articles.0.slug', 'comunicacoes')
            ->where('help.articles.0.url', route('panel.help.show', 'comunicacoes')));
    });

    it('offers nothing on screens without an article and inside the help center itself', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->get(route('panel.help.index'))->assertInertia(fn ($page) => $page->where('help', null));
        $this->get(route('panel.help.show', 'comunicacoes'))->assertInertia(fn ($page) => $page->where('help', null));
    });

    it('only offers articles the person is allowed to read', function () {
        // O artigo de Comunicações exige `manage-users`; quem só opera jogos não o vê, mesmo na tela certa.
        $operator = panelUser(Role::Operator);
        $this->actingAs($operator);

        $request = Request::create('/painel/communications');
        $request->setUserResolver(fn () => $operator);
        $request->setRouteResolver(fn () => (new LaravelRoute('GET', '/painel/communications', []))->name('panel.communications.edit'));

        expect(ContextualHelp::for($request))->toBeNull();
    });
});

describe('"isso ajudou?"', function () {
    it('keeps one vote per person and article, the last one counting', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->post(route('panel.help.feedback', 'comunicacoes'), ['helpful' => true])->assertSessionHas('success');
        $this->post(route('panel.help.feedback', 'comunicacoes'), ['helpful' => false])->assertSessionHas('success');

        expect(HelpFeedback::query()->count())->toBe(1)
            ->and(HelpFeedback::query()->first()->helpful)->toBeFalse()
            ->and(HelpFeedback::query()->first()->organization_id)->toBe(CurrentOrganization::id());

        $this->get(route('panel.help.show', 'comunicacoes'))->assertInertia(fn ($page) => $page->where('feedback', false)->where('routes.feedback', 'panel.help.feedback'));
    });

    it('shows no vote to someone who has not voted', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.show', 'comunicacoes'))->assertInertia(fn ($page) => $page->where('feedback', null));
    });

    it('refuses articles the person cannot see, bad values and guests', function () {
        $this->post(route('panel.help.feedback', 'comunicacoes'), ['helpful' => true])->assertRedirect();

        $operator = panelUser(Role::Operator);
        $this->actingAs($operator)->post(route('panel.help.feedback', 'comunicacoes'), ['helpful' => true])->assertNotFound();
        $this->post(route('panel.help.feedback', 'nao-existe'), ['helpful' => true])->assertNotFound();

        $admin = panelUser(Role::Admin);
        $this->actingAs($admin)->post(route('panel.help.feedback', 'comunicacoes'), ['helpful' => 'talvez'])->assertSessionHasErrors('helpful');

        expect(HelpFeedback::query()->count())->toBe(0);
    });

    it('tells the platform which articles most need work', function () {
        foreach ([1, 2, 3] as $n) {
            $user = panelUser(Role::Admin);
            HelpFeedback::query()->create(['user_id' => $user->id, 'slug' => 'comunicacoes', 'helpful' => $n === 3, 'organization_id' => CurrentOrganization::id()]);
        }

        HelpFeedback::query()->create(['user_id' => panelUser(Role::Admin)->id, 'slug' => 'artigo-removido', 'helpful' => false]);

        $this->actingAs(contextPlatformAdmin())->get(route('platform.help.index'))->assertInertia(fn ($page) => $page
            ->where('feedback_summary', fn ($list) => count($list) === 1 && $list[0]['slug'] === 'comunicacoes' && $list[0]['no'] === 2 && $list[0]['yes'] === 1));

        $this->actingAs(panelUser(Role::Admin))->get(route('panel.help.index'))->assertInertia(fn ($page) => $page->missing('feedback_summary'));
    });
});
