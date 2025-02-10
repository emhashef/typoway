<?php

namespace Emhashef\Typoway\Support\TsTypes;

interface TsType
{
    public function toTs($inline = false): string;
    public function toObj($inline = false): string;
}
