<?php

namespace Tests;

use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Os testes de backend não dependem do build do front (manifest do Vite).
        $this->withoutVite();

        // A organização ativa é estado estático: limpa entre os testes.
        CurrentOrganization::forget();
    }

    protected function tearDown(): void
    {
        CurrentOrganization::forget();

        parent::tearDown();
    }
}
