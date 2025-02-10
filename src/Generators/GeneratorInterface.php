<?php

namespace Emhashef\Typoway\Generators;

use Emhashef\Typoway\RoutesFiles;

interface GeneratorInterface
{
    public function generate(RoutesFiles $filesManager, array $routes): void;
}
