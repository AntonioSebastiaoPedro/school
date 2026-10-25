<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase apaga todas as tabelas: nunca pode correr contra a base de desenvolvimento.
        $database = config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with((string) $database, '_test')) {
            throw new \RuntimeException("Os testes recusam correr na base «{$database}». Use ./php.sh vendor/bin/pest.");
        }
    }
}
