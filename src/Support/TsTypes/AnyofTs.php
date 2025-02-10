<?php

namespace Emhashef\Typoway\Support\TsTypes;

class AnyofTs implements TsType
{
    public function __construct(protected array $types)
    {
        foreach ($this->types as $type) {
            if (!$type instanceof TsType) {
                throw new \InvalidArgumentException(
                    "Anyof array must be an instance of TsType",
                );
            }
        }
    }

    public function toTs($inline = false): string
    {
        return implode(" | ", array_map(fn($type) => $type->toTs($inline), $this->types));
    }

    public function toObj($inline = false): string
    {
        return isset($this->types[0]) ? $this->types[0]->toObj($inline) : 'null';
    }
}
