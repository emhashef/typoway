<?php

namespace Emhashef\Typoway\Support\TsTypes;

class ArrayTs implements TsType
{
    public function __construct(protected TsType $type)
    {
    }

    public function toTs($inline = false): string
    {
        return $this->type->toTs($inline) . "[]";
    }

    public function toObj($inline = false): string
    {
        return "[]";
    }
}
