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
}