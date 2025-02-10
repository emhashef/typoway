<?php

namespace Emhashef\Typoway;

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
    public function __construct(protected TypeTransformer $typeTransformer) {}

    public function extract(Route $route): TsType
    {
        return new ObjectTs([
            ...$this->extractAccessedRequestProperties($route),
            ...$this->extractParamsFromScramble(app(RouteInfo::class, ["route" => $route])),
        ]);
    }

    protected function extractParamsFromScramble(RouteInfo $route): array
    {
        if (!$route->isClassBased()) {
            return [];
        }

        $typeDefiningHandlers = [
            new FormRequestRulesExtractor($route->methodNode(), $this->typeTransformer),
            new ValidateCallExtractor($route->methodNode(), $this->typeTransformer),
            new RequestMethodCallsExtractor(),
        ];

        $params = collect($typeDefiningHandlers)
            ->filter(fn($h) => $h->shouldHandle())
            ->map(fn($h) => $h->extract($route))
            ->values()
            ->map(fn($r) => $r->parameters)
            ->flatten();

        $params = collect((new DeepParametersMerger($params))->handle());

        return $params->mapWithKeys(
                fn(Parameter $result) => [
                    $result->name => $this->getType($result->schema->type),
                ],
            )
            ->toArray();
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
            ->mapWithKeys(fn($value) => [$value => new StrictTs("string")])
            ->toArray();
    }

    protected function matchAll($body, $pattern)
    {
        return str($body)->matchAll($pattern)->toArray();
    }
}
