<?php

namespace Emhashef\Typoway;

use Dedoc\Scramble\Scramble;
use Emhashef\Typoway\Console\Commands\GenerateRoutes;
use Emhashef\Typoway\ScrambleExtensions\InertiaResponseExtension;
use Emhashef\Typoway\ScrambleExtensions\InertiaTypeToSchema;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateRoutes::class]);
        }

        $this->publishes([
            __DIR__.'/../config/typoway.php' => config_path('typoway.php'),
        ], 'typoway');

        Scramble::routes(fn() => true);

        Scramble::registerExtensions([
            InertiaResponseExtension::class,
            InertiaTypeToSchema::class
        ]);


    }
}
