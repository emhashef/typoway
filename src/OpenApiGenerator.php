<?php

namespace Emhashef\Typoway;

use Dedoc\Scramble\Generator;

class OpenApiGenerator
{
    protected array $openApi;

    public function __construct(protected Generator $scrambleGenerator)
    {
        
    }

    public function __invoke()
    {
        return $this->openApi ??= $this->scrambleGenerator->__invoke();
    }
}