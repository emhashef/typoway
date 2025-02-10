<?php

namespace  Emhashef\Typoway\ScrambleExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node\Expr;
use Dedoc\Scramble\Support\Type\Type;


class InertiaTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(\Inertia\Inertia::class);
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        $template = $type->templateTypes[0];

        if($template instanceof KeyedArrayType){
            foreach($template->items as $index => $item){
                if($item->value instanceof FunctionType){
                    $template->items[$index]->value = $item->value->returnType;
                }
            }
        }

        // foreach()
        return $this->openApiTransformer->transform($template);
    }
}
