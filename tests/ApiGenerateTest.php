<?php

namespace Emhashef\Typoway\Tests;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Concerns\HandlesRoutes;

class TestController extends Controller
{
    public function index(Request $request )
    {
        return [
            'name' => $request->name,
        ];
    }

    public function storeArray(Request $request)
    {
        $request->validate([
            'bob' => 'array',
            'bob.*' => 'integer'
        ]);
    }

    public function storeObject(Request $request)
    {
        $request->validate([
            'bob' => 'array',
            'bob.name' => 'string',
            'bob.phone' => 'integer'
        ]);
    }
}

class ApiGenerateTest extends TestCase
{
    public function defineRoutes($router)
    {
        $router->get('/api/test', [TestController::class, 'index'])->name('test.index');
        $router->get('/api/without-name', [TestController::class, 'index']);

        $router->post('/api/store-array', [TestController::class, 'storeArray'])->name('test.storeArray');

        $router->post('/api/store-object', [TestController::class, 'storeObject'])->name('test.storeObject');
    }

    public function test_api_generate_with_request_param_and_json_response()
    {
        $this->artisan('typoway:generate --apis')
            ->expectsOutput('Routes generated successfully')
            ->assertExitCode(0);

        $this->assertFileExists(resource_path("js/routes.apis.setup.ts"));
        $this->assertFileExists(resource_path("js/routes.urls.setup.ts"));

        $this->assertFileExists(resource_path("js/routes.ts"));

        $this->assertStringContainsString(
            "index: ( data?: {name?: string}, ): ApiResponse<{name?: string}> => request('get', urls.test.index( ), data)",
            file_get_contents(resource_path("js/routes.ts"))
        );
    }

    public function test_api_generate_for_routes_without_name()
    {
        $this->artisan('typoway:generate --apis')
            ->expectsOutput('Routes generated successfully')
            ->assertExitCode(0);

        $this->assertFileExists(resource_path("js/routes.apis.setup.ts"));
        $this->assertFileExists(resource_path("js/routes.urls.setup.ts"));

        $this->assertFileExists(resource_path("js/routes.ts"));

        $this->assertStringNotContainsString(
            "index: ( data?: {name?: string}, ): ApiResponse<{name?: string}> => request('get', urls.test.withoutName( ), data)",
            file_get_contents(resource_path("js/routes.ts"))
        );
    }

    public function test_api_generate_for_routes_with_array_request()
    {
        $this->artisan('typoway:generate --apis')
            ->expectsOutput('Routes generated successfully')
            ->assertExitCode(0);

        $this->assertFileExists(resource_path("js/routes.apis.setup.ts"));
        $this->assertFileExists(resource_path("js/routes.urls.setup.ts"));

        $this->assertFileExists(resource_path("js/routes.ts"));

        $this->assertStringContainsString(
            "storeArray: ( data?: {bob?: number[]}, ): ApiResponse<any> => request('post', urls.test.storeArray( ), data)",
            file_get_contents(resource_path("js/routes.ts"))
        );
    }

    public function test_api_generate_for_routes_with_object_request()
    {
        $this->artisan('typoway:generate --apis')
            ->expectsOutput('Routes generated successfully')
            ->assertExitCode(0);

        $this->assertFileExists(resource_path("js/routes.apis.setup.ts"));
        $this->assertFileExists(resource_path("js/routes.urls.setup.ts"));

        $this->assertFileExists(resource_path("js/routes.ts"));

        $this->assertStringContainsString(
            "storeObject: ( data?: {bob?: {name?: string;phone?: number}}, ): ApiResponse<any> => request('post', urls.test.storeObject( ), data)",
            file_get_contents(resource_path("js/routes.ts"))
        );
    }
}