<?php

namespace Emhashef\Typoway\Tests;

use Dedoc\Scramble\ScrambleServiceProvider;
use Emhashef\Typoway\ServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;
 
class TestCase extends BaseTestCase
{
    use WithWorkbench;
    
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
            ScrambleServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup Scramble configuration
        $app['config']->set('scramble', [
            'context' => [
                'info' => [
                    'title' => 'API Documentation',
                    'version' => '1.0.0',
                ],
            ],
            'servers' => null,
            'extensions' => [],
            'security' => [],
            'auth' => [],
            'examples' => [
                'faker_seed' => null,
            ],
        ]);
    }
}