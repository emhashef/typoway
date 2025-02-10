<?php

namespace Emhashef\Typoway\Support\TsTypes;

class StrictTs implements TsType
{


    public function __construct(protected string $type, protected bool $isString = false)
    {

    }

    public function toTs($inline = false): string
    {
        return $this->isString ? "'$this->type'" : $this->type;
    }

    public function toObj($inline = false): string
    {
        return 'null';
    }
}