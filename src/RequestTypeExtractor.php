<?php

namespace Emhashef\Typoway;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RequestMethodCallsExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ValidateCallExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Emhashef\Typoway\Support\TsTypes\ArrayTs;
use Emhashef\Typoway\Support\TsTypes\ObjectTs;
use Emhashef\Typoway\Support\TsTypes\StrictTs;
use Emhashef\Typoway\Support\TsTypes\TsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionClass;

class RequestTypeExtractor
{
    protected array $openApi;

    public function __construct(protected OpenApiGenerator $openApiGenerator) {}

    public function extract(Route $route): TsType
    {
        return new ObjectTs([
            ...$this->extractAccessedRequestProperties($route),
            ...$this->extractParamsFromScramble($route),
        ]);
    }

    protected function extractParamsFromScramble(Route $route): array
    {
        $this->openApi ??= $this->openApiGenerator->__invoke();
        
        $path = (string) str(ltrim($route->uri(), '/'))->replaceFirst(ltrim(config('scramble.api_path', 'api'), '/'), '')->ltrim('/')->prepend('/');
        $method = strtolower($route->methods()[0]);
        
        if (!isset($this->openApi['paths'][$path][$method])) {
            return [];
        }
        
        $operation = $this->openApi['paths'][$path][$method];
        $result = [];

        // Handle path and query parameters
        $parameters = $operation['parameters'] ?? [];
        foreach ($parameters as $parameter) {
            $name = $parameter['name'];
            $schema = $parameter['schema'] ?? [];
            $result[$name] = $this->convertSchemaToTsType($schema);
        }

        // Handle request body parameters
        if (isset($operation['requestBody']['content']['application/json']['schema'])) {
            $schema = $operation['requestBody']['content']['application/json']['schema'];
            if ($schema['type'] === 'object' && isset($schema['properties'])) {
                foreach ($schema['properties'] as $name => $property) {
                    $result[$name] = $this->convertSchemaToTsType($property);
                }
            }
        }
        
        return $result;
    }
    
    protected function convertSchemaToTsType(array $schema): TsType
    {
        if (empty($schema)) {
            return new StrictTs("any");
        }
        
        $type = $schema['type'] ?? null;
        
        return match ($type) {
            'string' => new StrictTs("string"),
            'number', 'integer' => new StrictTs("number"),
            'array' => new ArrayTs(
                $this->convertSchemaToTsType($schema['items'] ?? [])
            ),
            'object' => new ObjectTs(
                collect($schema['properties'] ?? [])->mapWithKeys(
                    fn ($property, $key) => [
                        $key => $this->convertSchemaToTsType($property)
                    ]
                )->toArray()
            ),
            default => new StrictTs("any"),
        };
    }

    protected function getType(Type $type)
    {
        return match (true) {
            $type instanceof StringType => new StrictTs("string"),
            $type instanceof NumberType => new StrictTs("number"),
            $type instanceof ArrayType => new ArrayTs($this->getType($type->items)),
            $type instanceof ObjectType => new ObjectTs(
                collect($type->properties)->mapWithKeys(
                    fn(Type $type,$key) => [
                        $key => $this->getType($type),         
                    ]
                )->toArray()),
            default => new StrictTs("any"),
        };
    }

    protected function extractAccessedRequestProperties(Route $route)
    {
        $controller = $route->getAction("controller");

        if (!$controller) {
            return [];
        }

        if (strpos($controller, "@") !== false) {
            [$controller, $method] = explode("@", $controller);
        } else {
            $method = "__invoke";
        }

        $reflection = new ReflectionClass($controller);
        $rmethod = $reflection->getMethod($method);
        $params = $rmethod->getParameters();

        $func = $rmethod;
        $filename = $func->getFileName();
        $start_line = $func->getStartLine() - 1;
        $end_line = $func->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        $body = implode("", array_slice($source, $start_line, $length));

        $result = [];
        foreach ($params as $param) {
            if (
                $param->getType() &&
                (get_parent_class($param->getType()->getName()) === FormRequest::class ||
                    $param->getType()->getName() === Request::class)
            ) {
                $paramName = $param->getName();

                foreach (["\\\${$paramName}", "request\(\)"] as $reqVar) {
                    foreach (
                        [
                            "/{$reqVar}->([a-zA-Z_\x7f-\xff][a-zA-Z0-9_]*)\b(?!\()/",
                            // "/{$reqVar}->get"
                        ]
                        as $regex
                    ) {
                        $result = array_merge($result, $this->matchAll($body, $regex));
                    }
                }
            }
        }

        return collect($result)
            ->filter()
            ->mapWithKeys(fn($value) => [$value => new StrictTs("any")])
            ->toArray();
    }

    protected function matchAll($body, $pattern)
    {
        return str($body)->matchAll($pattern)->toArray();
    }
}
