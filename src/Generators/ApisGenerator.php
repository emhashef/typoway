<?php

namespace Emhashef\Typoway\Generators;

use Emhashef\Typoway\RequestTypeExtractor;
use Emhashef\Typoway\ResponseTypeExtractor;
use Emhashef\Typoway\RoutesFiles;
use Emhashef\Typoway\RoutesFilesManager;
use Emhashef\Typoway\Support\Utils;

class ApisGenerator implements GeneratorInterface
{
    public function __construct(
        protected Utils $utils,
        protected RequestTypeExtractor $requestTypeExtractor,
        protected ResponseTypeExtractor $responseTypeExtractor,
    ) {
    }

    /**
     * @param \Illuminate\Routing\Route[] $routes
     */
    public function generate(RoutesFiles $filesManager, array $routes): void
    {
        $filesManager->addPrerequisite(
            "apis",
            $this->prerequisite(),
            "request, ApiResponse",
        );

        $structure = [];
        /** @var \Illuminate\Routing\Route $route  */
        foreach ($routes as $route) {
            [$refs, $responseType] = $this->responseTypeExtractor->extract($route);

            $filesManager->addReference($refs);

            $responseType = $responseType->toTs(true);

            $this->utils->buildNestedStructureForJavascript(
                $structure,
                $route->getName(),
                sprintf(
                    "(%s data?: %s, %s): ApiResponse<$responseType> => request('%s', %s(%s %s), data)",
                    $this->utils->getRequiredParametersAsArgument($route),
                    $this->requestTypeExtractor->extract($route)->toTs(true),
                    $this->utils->getOptionalParametersAsArgument($route),
                    $this->utils->getMethod($route),
                    $this->utils->getUrlAccessor($route),
                    join(",", $pars = $this->utils->getRequiredParameters($route)) .
                        ($pars ? "," : ""),
                    join(
                        ",",
                        array_map(
                            fn($i) => "options.params." . $i,
                            $this->utils->getOptionalParameters($route),
                        ),
                    ),
                ),
            );
        }

        $filesManager->addExport(
            "apis",
            $this->utils->buildJavascriptObject($structure, true),
        );
    }

    public function prerequisite(): string
    {
        return <<<JS
        import axios from "axios";

        export type ApiResponse<T> = Promise<T>

        export async function request(method, url = "", data = {}) {
            const response = await axios({
                method: method,
                url: url,
                data: data,
            });
            return response.data
        }
        JS;
    }
}
