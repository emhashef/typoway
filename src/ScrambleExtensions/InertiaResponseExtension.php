<?php

namespace  Emhashef\Typoway\ScrambleExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node;
use PhpParser\Node\Expr;
use Dedoc\Scramble\Support\Type\Type;

class InertiaResponseExtension implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (
            $node instanceof Node\Expr\StaticCall &&
            ($node->class instanceof Node\Name &&
                is_a($node->class->toString(), \Inertia\Inertia::class, true))
        ) {
            return new Generic(\Inertia\Inertia::class, [
                TypeHelper::getArgType($scope, $node->args, ["props", 1]),
            ]);
        }

        return null;
    }
}
