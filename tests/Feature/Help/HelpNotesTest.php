<?php

use App\Modules\Help\Models\HelpNote;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    HelpLibrary::flush();
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function notePayload(array $override = []): array
{
    return $override + ['audience' => 'panel', 'title' => 'Como pedir a camisa nova', 'body' => "Fale com a secretaria.\n\n- Preencha o formulário\n- Aguarde o aviso"];
}

function noteSlugs($page, string $key = 'categories'): array
{
    return collect($page->toArray()['props'][$key] ?? [])->flatMap(fn ($category) => collect($category['articles'] ?? [$category])->pluck('slug'))->all();
}

describe('writing notes', function () {
    it('lets the administrator create, edit and delete notes, only inside the organization', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->post(route('panel.help-notes.store'), notePayload())->assertSessionHas('success');
        $note = HelpNote::query()->firstOrFail();

        expect($note->organization_id)->toBe(CurrentOrganization::id())->and($note->audience)->toBe('panel')->and($note->created_by)->toBe($admin->id);

        $this->patch(route('panel.help-notes.update', $note), notePayload(['title' => 'Camisa nova (atualizado)']))->assertSessionHas('success');
        expect($note->fresh()->title)->toBe('Camisa nova (atualizado)');

        $this->get(route('panel.help-notes.index'))->assertInertia(fn ($page) => $page->component('Panel/HelpNotes/Index')->has('notes', 1)->where('limit', HelpNote::LIMIT));

        $this->delete(route('panel.help-notes.destroy', $note))->assertSessionHas('success');
        expect(HelpNote::query()->count())->toBe(0);
    });

    it('never touches the notes of another organization', function () {
        $other = Organization::factory()->create(['slug' => 'outra']);
        $foreign = CurrentOrganization::using($other, fn () => HelpNote::query()->create(['audience' => 'panel', 'title' => 'Da outra', 'body' => 'Texto']));
        $this->actingAs(panelUser(Role::Admin));

        $this->patch(route('panel.help-notes.update', $foreign->id), notePayload())->assertNotFound();
        $this->delete(route('panel.help-notes.destroy', $foreign->id))->assertNotFound();
        $this->get(route('panel.help-notes.index'))->assertInertia(fn ($page) => $page->has('notes', 0));

        expect(CurrentOrganization::using($other, fn () => HelpNote::query()->count()))->toBe(1);
    });

    it('validates the fields and stops at the limit', function () {
        $admin = panelUser(Role::Admin);

        $this->actingAs($admin)->post(route('panel.help-notes.store'), notePayload(['audience' => 'todos', 'title' => '', 'body' => '']))->assertSessionHasErrors(['audience', 'title', 'body']);
        $this->post(route('panel.help-notes.store'), notePayload(['title' => str_repeat('a', 121)]))->assertSessionHasErrors('title');
        $this->post(route('panel.help-notes.store'), notePayload(['body' => str_repeat('a', 5001)]))->assertSessionHasErrors('body');

        foreach (range(1, HelpNote::LIMIT) as $n) {
            HelpNote::query()->create(['audience' => 'panel', 'title' => "Nota {$n}", 'body' => 'Texto']);
        }

        $this->post(route('panel.help-notes.store'), notePayload())->assertSessionHas('error');
        expect(HelpNote::query()->count())->toBe(HelpNote::LIMIT);
    });

    it('is for administrators only', function () {
        $this->actingAs(panelUser(Role::Organizer))->get(route('panel.help-notes.index'))->assertForbidden();
        $this->actingAs(panelUser(Role::Organizer))->post(route('panel.help-notes.store'), notePayload())->assertRedirect();

        expect(HelpNote::query()->count())->toBe(0);
    });
});

describe('reading notes in the help center', function () {
    it('shows a panel note to the panel team, with search, and renders it as plain text', function () {
        HelpNote::query()->create(['audience' => 'panel', 'title' => 'Camisa nova', 'body' => "Chame a secretaria <b>agora</b>.\n\n- Passo um"]);
        $note = HelpNote::query()->firstOrFail();
        $slug = 'nota-'.strtolower($note->id);

        $this->actingAs(panelUser(Role::Viewer));

        $this->get(route('panel.help.index'))->assertInertia(fn ($page) => $page->where('categories', fn ($list) => in_array($slug, noteSlugs($page), true) && collect($list)->contains(fn ($category) => $category['name'] === 'Notas da organização')));
        $this->get(route('panel.help.index', ['q' => 'camisa']))->assertInertia(fn ($page) => $page->where('results', fn ($results) => collect($results)->pluck('slug')->contains($slug)));
        $this->get(route('panel.help.show', $slug))->assertOk()->assertInertia(fn ($page) => $page
            ->where('article.title', 'Camisa nova')
            ->where('article.blocks.0.inline.0.v', fn ($text) => str_contains($text, '<b>agora</b>')));
    });

    it('keeps manager notes for managers and panel notes for the panel', function () {
        HelpNote::query()->create(['audience' => 'panel', 'title' => 'Só equipe', 'body' => 'Texto']);
        HelpNote::query()->create(['audience' => 'manager', 'title' => 'Só gestor', 'body' => 'Texto']);
        $panel = HelpNote::query()->where('audience', 'panel')->firstOrFail();
        $managerNote = HelpNote::query()->where('audience', 'manager')->firstOrFail();

        $this->actingAs(managerFor(Team::factory()->create()));
        $this->get(route('manager.help.show', 'nota-'.strtolower($managerNote->id)))->assertOk();
        $this->get(route('manager.help.show', 'nota-'.strtolower($panel->id)))->assertNotFound();

        $this->actingAs(panelUser(Role::Viewer));
        $this->get(route('panel.help.show', 'nota-'.strtolower($panel->id)))->assertOk();
        $this->get(route('panel.help.show', 'nota-'.strtolower($managerNote->id)))->assertNotFound();
    });

    it('does not show the notes of one organization to the people of another', function () {
        $other = Organization::factory()->create(['slug' => 'outra']);
        $foreign = CurrentOrganization::using($other, fn () => HelpNote::query()->create(['audience' => 'panel', 'title' => 'Da outra', 'body' => 'Texto']));

        $this->actingAs(panelUser(Role::Viewer))->get(route('panel.help.show', 'nota-'.strtolower($foreign->id)))->assertNotFound();
    });

    it('accepts the "isso ajudou?" vote on a note', function () {
        $note = HelpNote::query()->create(['audience' => 'panel', 'title' => 'Camisa nova', 'body' => 'Texto']);

        $this->actingAs(panelUser(Role::Viewer))->post(route('panel.help.feedback', 'nota-'.strtolower($note->id)), ['helpful' => true])->assertSessionHas('success');
    });
});
