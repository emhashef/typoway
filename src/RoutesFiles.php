<?php

namespace Emhashef\Typoway;

use Emhashef\Typoway\Support\TsTypes\TsType;
use Illuminate\Support\Arr;
use function Illuminate\Filesystem\join_paths;

class RoutesFiles
{
    protected $references = [];

    protected $prerequisite = [];

    protected $imports = [];

    protected $exports = [];

    public function addReference(string|array $uniqueName,  $content = null)
    {
        if (is_array($uniqueName)) {
            $this->references = array_merge($uniqueName, $this->references);
        } else {
            $this->references[$uniqueName] = $content;
        }
    }

    public function addPrerequisite(
        string $key,
        string $prerequisite,
        string $importsInRouteFile,
    ) {
        $this->prerequisite[$key] = [$prerequisite, $importsInRouteFile];
    }

    /**
     * add js variable names you wan't to be imported from the prerequisite file
     * @param string $import
     * @return void
     */
    public function addImportFromPrerequisite(string $import)
    {
        $this->imports[] = $import;
    }

    public function addExport(string $export, string $content)
    {
        $this->exports[$export] = $content;
    }

    public function save($routesFileName = "routes", $path = null)
    {
        $path = $path ?? resource_path("js");

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        foreach ($this->prerequisite as $key => $item) {
            if (
                !file_exists(
                    join_paths(
                        $path,
                        $fn = $routesFileName . "." . $key . ".setup.ts",
                    ),
                )
            ) {
                file_put_contents(join_paths($path, $fn), $item[0]);
            }
        }

        file_put_contents(
            join_paths($path, $routesFileName . '.ts'),
            join("\n", [
                ...array_values(Arr::map(
                    $this->prerequisite,
                    fn($import, $key) => "import { {$import[1]} } from './" .
                        $routesFileName .
                        "." .
                        $key .
                        "." .
                        "setup" .
                        "';",
                )),
                ...Arr::map(
                    $this->references,
                    fn($reference, $key) => "export type $key = {$reference->toTs(
                        true,
                    )};\n",
                ),
                ...Arr::map(
                    $this->exports,
                    fn($content, $export) => "export const $export = $content;",
                ),
            ]),
        );
    }
}
