<?php

use App\Models\User;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Support\Cpf;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));

    $this->actingAs(User::factory()->create());
});

it('lists people with search by name, nickname and full CPF', function () {
    Person::factory()->create(['name' => 'Carlos Eduardo Lima', 'nickname' => 'Cadu', 'document_hash' => Cpf::hash('529.982.247-25')]);
    Person::factory()->create(['name' => 'Marcos Paulo']);

    $this->get('/painel/people?q=Eduardo')->assertInertia(fn ($page) => $page
        ->component('Panel/People/Index')->has('people.data', 1)->where('people.data.0.name', 'Carlos Eduardo Lima')->where('people.data.0.has_document', true));

    $this->get('/painel/people?q=Cadu')->assertInertia(fn ($page) => $page->has('people.data', 1));
    $this->get('/painel/people?q=529.982.247-25')->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.name', 'Carlos Eduardo Lima'));
    $this->get('/painel/people?q=111.444.777-35')->assertInertia(fn ($page) => $page->has('people.data', 0));
    $this->get('/painel/people')->assertInertia(fn ($page) => $page->has('people.data', 2));
});

it('creates a person storing only the CPF hash, never the CPF itself', function () {
    $this->post('/painel/people', [
        'name' => 'Carlos Eduardo Lima', 'nickname' => 'Cadu', 'birth_date' => '2001-04-15', 'document' => '529.982.247-25',
    ])->assertRedirect(route('panel.people.index'));

    $person = Person::query()->firstOrFail();

    expect($person->document_hash)->toBe(Cpf::hash('52998224725'))
        ->and($person->birth_date->format('Y-m-d'))->toBe('2001-04-15');

    $stored = json_encode(DB::table('people')->first());
    expect($stored)->not->toContain('52998224725')->and($stored)->not->toContain('529.982.247-25');

    $this->get("/painel/people/{$person->id}/edit")->assertInertia(fn ($page) => $page
        ->where('person.has_document', true)
        ->missing('person.document')
        ->missing('person.document_hash'));
});

it('rejects an invalid CPF, a future birth date and a duplicated CPF', function () {
    $this->post('/painel/people', ['name' => 'A', 'document' => '123.456.789-00'])
        ->assertSessionHasErrors(['document' => 'O CPF informado não é válido.']);

    $this->post('/painel/people', ['name' => 'A', 'birth_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('birth_date');
    $this->post('/painel/people', ['name' => ''])->assertSessionHasErrors('name');

    Person::factory()->create(['document_hash' => Cpf::hash('529.982.247-25')]);
    $this->post('/painel/people', ['name' => 'Outro', 'document' => '52998224725'])
        ->assertSessionHasErrors(['document' => 'Já existe uma pessoa cadastrada com este CPF.']);
});

it('allows the same CPF in another organization', function () {
    Person::factory()->create(['organization_id' => Organization::factory()->create()->id, 'document_hash' => Cpf::hash('529.982.247-25')]);

    $this->post('/painel/people', ['name' => 'Carlos', 'document' => '529.982.247-25'])->assertSessionHasNoErrors();

    expect(Person::query()->count())->toBe(1);
});

it('keeps the CPF when updating without one and replaces it when a new one is sent', function () {
    $person = Person::factory()->create(['document_hash' => Cpf::hash('529.982.247-25')]);

    $this->put("/painel/people/{$person->id}", ['name' => 'Novo Nome', 'document' => ''])->assertRedirect();
    expect($person->fresh()->name)->toBe('Novo Nome')->and($person->fresh()->document_hash)->toBe(Cpf::hash('52998224725'));

    $this->put("/painel/people/{$person->id}", ['name' => 'Novo Nome', 'document' => '111.444.777-35'])->assertRedirect();
    expect($person->fresh()->document_hash)->toBe(Cpf::hash('11144477735'));

    $this->put("/painel/people/{$person->id}", ['name' => 'Novo Nome', 'document' => '111.444.777-35'])->assertSessionHasNoErrors();
});

it('blocks deleting a person who is in a roster or has game history', function () {
    $inRoster = RosterEntry::factory()->create();
    $this->delete("/painel/people/{$inRoster->person_id}")->assertSessionHas('error');
    expect(Person::query()->whereKey($inRoster->person_id)->exists())->toBeTrue();

    $withEvent = Person::factory()->create();
    GameEvent::factory()->create(['person_id' => $withEvent->id]);
    $this->delete("/painel/people/{$withEvent->id}")->assertSessionHas('error');

    $free = Person::factory()->create();
    $this->delete("/painel/people/{$free->id}")->assertSessionHas('success');
    expect(Person::query()->whereKey($free->id)->exists())->toBeFalse();
});

it('does not expose people of another organization', function () {
    $foreign = Person::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->get("/painel/people/{$foreign->id}/edit")->assertNotFound();
    $this->put("/painel/people/{$foreign->id}", ['name' => 'X'])->assertNotFound();
    $this->delete("/painel/people/{$foreign->id}")->assertNotFound();
});
