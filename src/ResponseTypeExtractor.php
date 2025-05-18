<?php

namespace Emhashef\Typoway;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Emhashef\Typoway\Support\TsTypes\ArrayTs;
use Emhashef\Typoway\Support\TsTypes\ObjectTs;
use Emhashef\Typoway\Support\TsTypes\StrictTs;
use Emhashef\Typoway\Support\TsTypes\TsType;
use Illuminate\Routing\Route;

class ResponseTypeExtractor
{
    protected array $openApi;

    public function __construct(protected OpenApiGenerator $openApiGenerator) {}

    public function extract(Route $route): array
    {
        $this->openApi ??= $this->openApiGenerator->__invoke();

        $path = (string) str(ltrim($route->uri(), '/'))->replaceFirst(ltrim(config('scramble.api_path', 'api'), '/'), '')->ltrim('/')->prepend('/');
        $method = strtolower($route->methods()[0]);
        
        if (!isset($this->openApi['paths'][$path][$method])) {
            return [[], new StrictTs("any")];
        }
        
        $operation = $this->openApi['paths'][$path][$method];
        $responses = $operation['responses'] ?? [];
        
        // Get the 200 response schema
        $successResponse = $responses['200'] ?? $responses['default'] ?? null;
        if (!$successResponse) {
            return [[], new StrictTs("any")];
        }
        
        $content = $successResponse['content'] ?? [];
        $jsonContent = $content['application/json'] ?? null;
        
        if (!$jsonContent || !isset($jsonContent['schema'])) {
            return [[], new StrictTs("any")];
        }
        
        $schema = $jsonContent['schema'];
        $references = [];
        $type = $this->convertSchemaToTsType($schema, $references);
        
        return [$references, $type];
    }
    
    protected function convertSchemaToTsType(array $schema, array &$references = []): TsType
    {
        if (empty($schema)) {
            return new StrictTs("any");
        }
        
        // Handle $ref
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            $components = $this->openApi['components']['schemas'] ?? [];
            $refName = basename($ref);
            
            if (isset($components[$refName])) {
                $references[$refName] = $this->convertSchemaToTsType($components[$refName], $references);
                return new StrictTs($refName);
            }
        }
        
        // Handle type
        $type = $schema['type'] ?? null;
        
        return match ($type) {
            'string' => new StrictTs("string"),
            'number', 'integer' => new StrictTs("number"),
            'boolean' => new StrictTs("boolean"),
            'array' => new ArrayTs(
                $this->convertSchemaToTsType($schema['items'] ?? [], $references)
            ),
            'object' => new ObjectTs(
                collect($schema['properties'] ?? [])->mapWithKeys(
                    function ($property, $key) use (&$references) {
                        return [
                            $key => $this->convertSchemaToTsType($property, $references)
                        ];
                    }
                )->toArray()
            ),
            default => new StrictTs("any"),
        };
    }

    protected function getType(Type $type): TsType
    {
        return match (true) {
            $type instanceof StringType => new StrictTs("string"),
            $type instanceof NumberType => new StrictTs("number"),
            $type instanceof BooleanType => new StrictTs("boolean"),
            $type instanceof ArrayType => new ArrayTs($this->getType($type->items)),
            $type instanceof ObjectType => new ObjectTs(
                collect($type->properties)->mapWithKeys(
                    fn(Type $type, $key) => [
                        $key => $this->getType($type),         
                    ]
                )->toArray()
            ),
            default => new StrictTs("any"),
        };
    }

    protected function collectReferences(?Type $type, array &$references): void
    {
        if (!$type) {
            return;
        }

        if ($type instanceof ObjectType) {
            foreach ($type->properties as $property) {
                $this->collectReferences($property, $references);
            }
        } elseif ($type instanceof ArrayType) {
            $this->collectReferences($type->items, $references);
        }
    }
}
