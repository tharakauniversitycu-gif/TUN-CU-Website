<?php
namespace Tests;

use Illuminate\Contracts\Console\Kernel;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        // Hard stop before migrations if an external test invocation overrides isolation.
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require isolated in-memory SQLite.');
        }
        config(['library.root' => storage_path('testing/library'), 'cache.default' => 'array']);
        return $app;
    }
}
