<?php

namespace Emhashef\Typoway\Support\TsTypes;

class ObjectTs implements TsType
{
    public function __construct(protected array $properties)
    {
        foreach ($properties as $name => $property) {
            if (!$property instanceof TsType || !is_string($name)) {
                throw new \InvalidArgumentException(
                    "Property array must be an instance string => TsType",
                );
            }
        }
    }

    public function toTs($inline = false): string
    {
        $properties = collect($this->properties)
            ->map(function (TsType $property, string $name) use ($inline) {
                return "$name?: " . $property->toTs($inline);
            })
            ->join($inline ? ";" : ";\n");

        return $inline ? "{" . $properties . "}" : "{\n$properties\n}";
    }

    public function toObj($inline = false): string
    {
        $properties = collect($this->properties)
            ->map(function (TsType $property, string $name) use ($inline) {
                return "$name:" . $property->toObj($inline);
            })
            ->join($inline ? "," : ",\n");

        return $inline ? "{" . $properties . "}" : "{\n$properties\n}";
    }
}
