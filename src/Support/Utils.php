<?php

namespace Emhashef\Typoway\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class Utils
{
    public function buildNestedStructureForJavascript(
        &$structure,
        string $key,
        string $value,
    ) {
        $key = str($key)->replace("-", "_");

        data_set($structure, $key, $value);
    }

    public function buildJavascriptObject(array $structure, bool $pretty = true)
    {
        $ts = "{\n";
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $ts .=
                    "    $key: " .
                    str($this->buildJavascriptObject($value), $pretty)->replace(
                        "\n",
                        "\n    ",
                    ) .
                    ",\n";
            } else {
                $ts .= "    $key: $value,\n";
            }
        }
        return rtrim($ts, ",\n") . "\n}";
    }

    public function getParameters(Route $route)
    {
        return str($route->uri())
            ->matchAll("/\{(\w+?)\?\}/")
            ->map(
                fn($parameter) => (string) str($parameter)
                    ->replace("export", "_export")
                    ->replace("import", "_import"),
            )
            ->reverse()
            ->toArray();
    }

    public function getRequiredParameters(Route $route)
    {
        return str($route->uri())
            ->matchAll("/\{([^\?\}]+)\}/")
            ->map(
                fn($parameter) => (string) str($parameter)
                    ->replace("export", "_export")
                    ->replace("import", "_import"),
            )
            ->toArray();
    }

    public function getRequiredParametersAsArgument(Route $route): string
    {
        $parameters = join(
            ",",
            array_map(
                fn($name) => (string) str($name)->append(": string"),
                $this->getRequiredParameters($route),
            ),
        );

        // $parameters = (string) str($parameters)
        //     ->replace("export", "_export")
        //     ->replace("import", "_import");

        return $parameters ? $parameters . "," : "";
    }

    public function getOptionalParameters(Route $route)
    {
        return str($route->uri())
            ->matchAll("/\{(\w+?)\?\}/")
            ->map(
                fn($parameter) => (string) str($parameter)
                    ->replace("export", "_export")
                    ->replace("import", "_import"),
            )
            ->reverse()
            ->toArray();
    }

    public function getOptionalParametersAsArgument(Route $route)
    {
        $parameters = join(
            ",",
            array_map(
                fn($name) => (string) str($name)->append(": string = ''"),
                $this->getOptionalParameters($route),
            ),
        );

        // $parameters = (string) str($parameters)
        //     ->replace("export", "_export")
        //     ->replace("import", "_import");

        return $parameters ? $parameters . "," : "";
    }

    public function getUrl(Route $route)
    {
        return (string) str($route->uri())
            ->replace("?", "")
            ->replace("{export}", "{_export}")
            ->replace("{import}", "{_import}")
            ->replace("{", '${')
            ->rtrim("/")
            ->prepend("/");
    }

    public function getUrlAccessor(Route $route)
    {
        return (string) str($route->getName())->replace("-", "_")->prepend("urls.");
    }

    public function getMethod(Route $route)
    {
        return Str::lower($route->methods()[0]);
    }

    public function convertPCREToJS($pcrePattern)
    {
        // Remove curly braces around the pattern if present
        $pattern = preg_replace('/^{(.*)}[a-zA-Z]*$/', '$1', $pcrePattern);

        // Convert named groups from `(?P<name>...)` to `(?<name>...)`
        $namedGroupPattern = preg_replace("/\(\?P<(\w+)>/", '(?<$1>', $pattern);

        // Convert possessive quantifiers `++`, `*+`, `?+` to regular greedy quantifiers `+`, `*`, `?`
        // $possessivePattern = preg_replace('/(\+\+|\*\+|\?\+)/', '$1[0]', $namedGroupPattern);
        $possessivePattern = str_replace(
            ["++", "*+", "?+"],
            ["+", "*", "?"],
            $namedGroupPattern,
        );

        $possessivePattern = str_replace("/", "\/", $possessivePattern);

        // Extract flags (e.g., 'sDu') from the original pattern, if any
        preg_match('/}([a-zA-Z]*)$/', $pcrePattern, $flagMatch);
        $flags = isset($flagMatch[1]) ? $flagMatch[1] : "";

        // Translate PCRE flags to JavaScript-compatible ones
        $jsFlags = "";
        if (strpos($flags, "u") !== false) {
            $jsFlags .= "u";
        } // Unicode flag is supported in JS
        if (strpos($flags, "i") !== false) {
            $jsFlags .= "i";
        } // Case-insensitive flag
        if (strpos($flags, "m") !== false) {
            $jsFlags .= "m";
        } // Multiline flag

        // Return the pattern and flags as a string formatted for JavaScript
        return "new RegExp('$possessivePattern', '$jsFlags')";
    }
}
