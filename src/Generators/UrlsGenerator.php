<?php

namespace Emhashef\Typoway\Generators;

use Emhashef\Typoway\RequestTypeExtractor;
use Emhashef\Typoway\ResponseTypeExtractor;
use Emhashef\Typoway\RoutesFiles;
use Emhashef\Typoway\RoutesFilesManager;
use Emhashef\Typoway\Support\Utils;

class UrlsGenerator implements GeneratorInterface
{
    public function __construct(protected Utils $utils) {}

    /**
     * @param \Illuminate\Routing\Route[] $routes
     */
    public function generate(RoutesFiles $filesManager, array $routes): void
    {
        $filesManager->addPrerequisite("urls", $this->prerequisite(), "_default");

        $matches = [];
        $structure = [];
        /** @var \Illuminate\Routing\Route $route  */
        foreach ($routes as $route) {
            $this->utils->buildNestedStructureForJavascript(
                $structure,
                $route->getName(),
                sprintf(
                    "(%s) => `%s`",
                    join(
                        ", ",
                        array_map(
                            fn($param) => "$param?: string",
                            $this->utils->getParameters($route),
                        ),
                    ),
                    str($this->utils->getUrl($route))->replace(
                        array_map(
                            fn($p) => "{" . $p . "}",
                            $this->utils->getParameters($route),
                        ),
                        array_map(
                            fn($p) => "{" . "$p ?? _default('$p')" . "}",
                            $this->utils->getParameters($route),
                        ),
                    ),
                ),
            );

            $this->utils->buildNestedStructureForJavascript(
                $matches,
                $route->getName(),
                "() => window.location.pathname.match(" .
                    $this->utils->convertPCREToJS($route->toSymfonyRoute()->compile()->getRegex()) .
                    ")",
            );
        }

        $filesManager->addExport("matches", $this->utils->buildJavascriptObject($matches, true));

        $filesManager->addExport("urls", $this->utils->buildJavascriptObject($structure, true));
    }

    public function prerequisite(): string
    {
        return <<<JS
        export function _default(param){
            return ''
        }
        JS;
    }
}
