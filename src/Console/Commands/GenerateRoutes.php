<?php

namespace Emhashef\Typoway\Console\Commands;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RequestMethodCallsExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ValidateCallExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Union;
use Emhashef\Typoway\Generators\ApisGenerator;
use Emhashef\Typoway\Generators\InertiaGenerator;
use Emhashef\Typoway\Generators\UrlsGenerator;
use Emhashef\Typoway\RoutesFiles;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ReflectionClass;

class GenerateRoutes extends Command
{
    protected $signature = "typoway:generate {--apis} {--inertia}";

    protected $description = "Command for generating typescript based routes";

    protected $exceptNames = [
        "filament*",
        "scramble*",
        "debugbar*",
        "dusk*",
        "ignition*",
        "livewire*",
    ];

    public function handle(
        UrlsGenerator $urlsGenerator, 
        ApisGenerator $apisGenerator, 
        InertiaGenerator $inertiaGenerator
    ){
        $routes = collect(Route::getRoutes())->filter(
            fn($route) => $route->getName() &&
                !str($route->getName())->is(config('typoway.except-routes', $this->exceptNames)),
        );

        /** @var \Illuminate\Routing\Route $route */
        foreach ($routes as $route) {
            // walk through all routes and check that the route
            // has a subscription in other routes
            if (
                $routes->first(
                    fn($r) => str($r->getName())
                        ->replaceStart($route->getName(), "")
                        ->startsWith("."),
                )
            ) {
                $route->name("._index");
            }
        }

        $apiRoutes = $routes->filter(
            fn(\Illuminate\Routing\Route $route) => str($route->uri())->startsWith(
                "api/",
            ),
        );

        $webRoutes = $routes->filter(
            fn($route) => !str($route->uri())->startsWith("api/"),
        );

        $file = new RoutesFiles();

        $urlsGenerator->generate($file, $routes->toArray());

        if ($this->shouldGenerate("apis")) {
            $apisGenerator->generate($file, $apiRoutes->toArray());
        }

        if ($this->shouldGenerate("inertia")) {
            $inertiaGenerator->generate($file, $webRoutes->toArray());
        }

        $file->save();

        $this->info("Routes generated successfully");
    }

    protected function shouldGenerate(string $type)
    {
        return $this->option($type) || empty($this->getOptions());
    }
}
