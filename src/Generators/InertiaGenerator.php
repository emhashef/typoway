<?php

namespace Emhashef\Typoway\Generators;

use Emhashef\Typoway\RequestTypeExtractor;
use Emhashef\Typoway\ResponseTypeExtractor;
use Emhashef\Typoway\RoutesFiles;
use Emhashef\Typoway\Support\Utils;

class InertiaGenerator implements GeneratorInterface
{
    public function __construct(
        protected Utils $utils,
        protected RequestTypeExtractor $requestTypeExtractor,
        protected ResponseTypeExtractor $responseTypeExtractor,
    ) {
    }

    public function generate(RoutesFiles $filesManager, array $routes): void
    {
        $filesManager->addPrerequisite(
            "inertia",
            $this->prerequisite(),
            "FormResponse, useForm, FormOptions, router",
        );

        $formsStructure = [];
        $routesStructure = [];
        /** @var \Illuminate\Routing\Route $route  */
        foreach ($routes as $route) {
            [$refs, $responseType] = $this->responseTypeExtractor->extract($route);

            $filesManager->addReference($refs);

            $filesManager->addReference(
                $reqTypeRef = str($route->getName())
                    ->slug(".")
                    ->replace(".", " ")
                    ->headline()
                    ->replace(" ", "")
                    ->append('Form'),
                $requestType = $this->requestTypeExtractor->extract($route),
            );

            $filesManager->addReference(
                str($route->getName())
                    ->slug(".")
                    ->replace(".", " ")
                    ->headline()
                    ->replace(" ", "")
                    ->append('Response'),

                $responseType,
            );

            $this->utils->buildNestedStructureForJavascript(
                $formsStructure,
                $route->getName(),
                sprintf(
                    "(%s data?: %s, options?: FormOptions): FormResponse<%s> => useForm<%s>(%s(%s %s), '%s', %s, options)",

                    $requiredArgs = $this->utils->getRequiredParametersAsArgument($route),
                    $reqTypeRef,
                    $reqTypeRef,
                    $reqTypeRef,
                    // $optionalArgs = $this->utils->getOptionalParametersAsArgument($route),
                    $url = $this->utils->getUrlAccessor($route),
                    join(",", $pars = $this->utils->getRequiredParameters($route)) .
                        ($pars ? "," : ""),
                    join(
                        ",",
                        array_map(
                            fn($i) => "options?.params?." . $i,
                            $this->utils->getOptionalParameters($route),
                        ),
                    ),
                    $method = $this->utils->getMethod($route),
                    "Object.assign(" . $requestType->toObj(true) . ", data)",
                ),
            );

            $this->utils->buildNestedStructureForJavascript(
                $routesStructure,
                $route->getName(),
                sprintf(
                    "(%s data?: %s, options?: FormOptions) => router.%s(%s(%s %s), %s, %s)",
                    $requiredArgs,
                    $reqTypeRef,
                    // $optionalArgs,
                    $method,
                    $url,
                    join(",", $pars = $this->utils->getRequiredParameters($route)) .
                        ($pars ? "," : ""),
                    join(
                        ",",
                        array_map(
                            fn($i) => "options?.params?." . $i,
                            $this->utils->getOptionalParameters($route),
                        ),
                    ),
                    $method == "delete" ? "{data, ...options}" : "data",
                    $method == "delete" ? "" : "options",
                ),
            );
        }

        $filesManager->addExport(
            "forms",
            $this->utils->buildJavascriptObject($formsStructure, true),
        );

        $filesManager->addExport(
            "routes",
            $this->utils->buildJavascriptObject($routesStructure, true),
        );
    }

    public function prerequisite(): string
    {
        return <<<JS
        import { InertiaForm, router } from "@inertiajs/vue3";
        import { useForm as inertiaUseForm } from "@inertiajs/vue3";
        import { VisitOptions, Method } from "@inertiajs/core";
        export { router } from "@inertiajs/vue3";

        export type FormResponse<T extends object> = {
            errors: { [key in keyof T]?: string | string[] };
            submit(_options?: Partial<VisitOptions>): void;
        } & InertiaForm<T>;

        export type FormOptions = Partial<VisitOptions & {params: {[x: string]: string}}>

        export function useForm<T extends object>(
            url,
            method: Method = "post",
            data: T = {} as T,
            options?: Partial<VisitOptions>,
        ): FormResponse<T> {
            const form = inertiaUseForm(data);
            const inertiaSubmit = form.submit.bind(form);
            return Object.assign(form, {
                submit: async (_options?: Partial<VisitOptions>) => {
                    const response = await inertiaSubmit(method, url, { ...options, ..._options });
                    return response;
                },
            });
        }
        JS;
    }
}
