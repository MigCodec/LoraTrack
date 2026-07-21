<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new \LogicException('Las pruebas no pueden ejecutarse con config:cache; ejecute php artisan config:clear para proteger la base de datos compartida.');
        }

        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Las pruebas deben usar exclusivamente SQLite en memoria.');
        }
    }
}
