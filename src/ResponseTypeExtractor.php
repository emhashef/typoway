<?php

namespace Emhashef\Typoway;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Emhashef\Typoway\Support\TsTypes\ArrayTs;
use Emhashef\Typoway\Support\TsTypes\ObjectTs;
use Emhashef\Typoway\Support\TsTypes\StrictTs;
use Emhashef\Typoway\Support\TsTypes\AnyofTs;
use Emhashef\Typoway\Support\TsTypes\TsType;
use Illuminate\Routing\Route;

class ResponseTypeExtractor
{
    public function __construct(protected TypeTransformer $typeTransformer)
    {
    }


    public function extract(Route $route): array
    {
        $route = app(RouteInfo::class, ["route" => $route]);
        $returnType = $route->getReturnType();

        if (!$returnType) {
            return [[], new StrictTs("any")];
        }

        $returnTypes = $returnType instanceof Union ? $returnType->types : [$returnType];

        $responses = collect($returnTypes)
            ->map($this->typeTransformer->toResponse(...))
            ->filter()
            ->filter(fn($response) => $response instanceof Response)
            ->filter(
                fn(Response $response) => $response->code == 200 &&
                    isset($response->content["application/json"]),
            )
            ->values();

        if (!$responses->first()) {
            return [[], new StrictTs("any")];
        }

        return $this->generateTsFromScrambleType(
            $responses->first()->content["application/json"]->type,
        );
    }

    public function generateTsFromScrambleType(Type $type): array
    {
        $references = [];
        $ts = new StrictTs("any");

        if ($type instanceof Reference) {
            [$refs, $nestedTs] = $this->generateTsFromScrambleType(
                $type->resolve()->type,
            );

            $references = array_merge($references, $refs, [
                $type->getUniqueName() => $nestedTs,
            ]);

            $ts = new StrictTs($type->getUniqueName());
        } elseif ($type instanceof AnyOf) {
            $ts = "";
            $types = [];
            foreach (invade($type)->items as $iType) {
                [$refs, $nestedTs] = $this->generateTsFromScrambleType($iType);
                $references = array_merge($references, $refs);
                $types[] = $nestedTs;
            }

            $ts = new AnyofTs($types);
        } elseif ($type instanceof ObjectType) {
            $properties = [];
            foreach ($type->properties as $field => $property) {
                [$refs, $nestedTs] = $this->generateTsFromScrambleType($property);


                $references = array_merge($references, $refs);

                $properties[$field] = $nestedTs;
            }

            $ts = new ObjectTs($properties);
        } elseif ($type instanceof ArrayType) {
            [$refs, $nestedTs] = $this->generateTsFromScrambleType($type->items);

            $references = array_merge($references, $refs);
            $ts = new ArrayTs($nestedTs);
        } elseif ($type instanceof UnknownType) {
            $ts = new StrictTs("any");
        } 
         elseif ($type instanceof StringType) {
            if (!empty($type->enum)) {
                $ts = new AnyofTs(
                    collect($type->enum)
                        ->map(fn($e) => new StrictTs($e, true))
                        ->toArray(),
                );
            } else {
                $ts = new StrictTs("string");
            }
        } elseif ($type instanceof NumberType) {
            $ts = new StrictTs("number");
        } elseif ($type instanceof BooleanType) {
            $ts = new StrictTs("boolean");
        } elseif ($type instanceof NullType) {
            $ts = new StrictTs("null");
        } elseif ($type instanceof MixedType) {
            $ts = new StrictTs("any");
        } else {
            throw new \Exception("Unknown type: " . get_class($type));
        }

        return [$references, $ts];
    }
}
