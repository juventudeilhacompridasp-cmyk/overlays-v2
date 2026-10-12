<?php

use App\Modules\Competitions\Models\Competition;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

it('only exposes records of the current organization', function () {
    $a = Organization::factory()->create();
    $b = Organization::factory()->create();

    Competition::factory()->count(2)->create(['organization_id' => $a->id]);
    Competition::factory()->create(['organization_id' => $b->id]);

    expect(Competition::query()->count())->toBe(3);

    CurrentOrganization::using($a, function () {
        expect(Competition::query()->count())->toBe(2);
    });

    CurrentOrganization::using($b, function () {
        expect(Competition::query()->count())->toBe(1);
    });

    expect(CurrentOrganization::get())->toBeNull();
});

it('fills organization_id from the current organization when creating', function () {
    $organization = Organization::factory()->create();

    $competition = CurrentOrganization::using($organization, fn () => Competition::query()->create([
        'name' => 'Copa Teste',
        'slug' => 'copa-teste',
    ]));

    expect($competition->organization_id)->toBe($organization->id);
});

it('keeps explicit organization_id even when another organization is current', function () {
    $current = Organization::factory()->create();
    $other = Organization::factory()->create();

    $competition = CurrentOrganization::using($current, fn () => Competition::query()->create([
        'organization_id' => $other->id,
        'name' => 'Copa Outra',
        'slug' => 'copa-outra',
    ]));

    expect($competition->organization_id)->toBe($other->id);
});

it('resolves the default organization on web requests', function () {
    $organization = Organization::factory()->create(['slug' => config('platform.default_organization_slug')]);

    $this->get('/up')->assertOk();
    $this->get('/')->assertOk();

    expect(CurrentOrganization::id())->toBe($organization->id);
});

it('creates the default organization on the first web request when it does not exist yet', function () {
    expect(Organization::query()->where('slug', config('platform.default_organization_slug'))->exists())->toBeFalse();

    $this->get('/')->assertOk();

    expect(CurrentOrganization::id())->not->toBeNull()
        ->and(Organization::query()->where('slug', config('platform.default_organization_slug'))->count())->toBe(1);
});
