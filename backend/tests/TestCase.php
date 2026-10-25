<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase apaga todas as tabelas: nunca pode correr contra a base de desenvolvimento,
        // nem fora do ambiente de teste (o .env do contentor pode sobrepor-se ao phpunit.xml).
        if (! app()->environment('testing')) {
            throw new \RuntimeException('Os testes exigem APP_ENV=testing. Use `make test`.');
        }

        $database = config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with((string) $database, '_test')) {
            throw new \RuntimeException("Os testes recusam correr na base «{$database}». Use `make test`.");
        }
    }
}
