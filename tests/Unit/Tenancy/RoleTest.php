<?php

use App\Modules\Tenancy\Enums\Ability;
use App\Modules\Tenancy\Enums\Role;

it('gives each role exactly the abilities of its area', function (Role $role, array $abilities) {
    expect($role->abilities())->toBe($abilities);

    foreach (Ability::cases() as $ability) {
        expect($role->can($ability))->toBe(in_array($ability, $abilities, true));
    }
})->with([
    'administrador' => [Role::Admin, Ability::cases()],
    'organizador' => [Role::Organizer, [Ability::ManageCompetitions, Ability::ManageRegistry, Ability::OperateGames]],
    'operador' => [Role::Operator, [Ability::OperateGames]],
    'consulta' => [Role::Viewer, []],
    'gestor de equipe' => [Role::TeamManager, []],
    'atleta' => [Role::Athlete, []],
]);

it('only lets administrators manage users', function () {
    $withUsers = array_filter(Role::cases(), fn (Role $role) => $role->can(Ability::ManageUsers));

    expect(array_values($withUsers))->toBe([Role::Admin]);
});

it('has a label for every role and ability', function () {
    foreach (Role::cases() as $role) {
        expect($role->label())->not->toBe('');
    }

    foreach (Ability::cases() as $ability) {
        expect($ability->label())->not->toBe('');
    }
});
