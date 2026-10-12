<?php

it('exposes the framework health endpoint', function () {
    $this->get('/up')->assertOk();
});

it('seeds the local demo admin user', function () {
    $this->seed();

    $this->assertDatabaseHas('users', ['email' => 'admin@plataforma.test']);
});
