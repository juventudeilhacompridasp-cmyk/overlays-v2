<?php

use App\Models\User;

it('exposes the framework health endpoint', function () {
    $this->get('/up')->assertOk();
});

it('seeds the local demo admin user', function () {
    $this->seed();

    $this->assertDatabaseHas('users', ['email' => 'admin@plataforma.test']);

    expect(User::where('email', 'admin@plataforma.test')->first()->hasVerifiedEmail())->toBeTrue();
});
