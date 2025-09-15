<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

class TxtFile
{
    /**
     * @var array<string,array{constraint:string|null}>
     */
    public private(set) array $plugins = [];

    public function __construct(
        public private(set) string $filePath,
    ) {
        $this->read();
    }

    /**
     * @return null|array{constraint:string|null}
     */
    public function getPluginConstraint(string $pluginKey): ?array
    {
        return $this->plugins[$pluginKey] ?? null;
    }

    public function addPlugin(string $pluginKey, string $versionConstraint): void
    {
        if (array_key_exists($pluginKey, $this->plugins)) {
            return;
        }

        $this->plugins[$pluginKey] = [
            'constraint' => $versionConstraint
        ];
    }

    public function removePlugin(string $pluginKey): void
    {
        unset($this->plugins[$pluginKey]);
    }

    public function write(): int|false
    {
        $contents = '';
        foreach ($this->plugins as $pluginKey => $version) {
            $contents .= $pluginKey . ($version['constraint'] === null ? '' : '@' . $version['constraint']) . PHP_EOL;
        }

        return file_put_contents($this->filePath, $contents);
    }

    private function read(): void
    {
        $pluginsTxt = (string) file_get_contents($this->filePath);

        if ($pluginsTxt === '') {
            return;
        }

        $pluginLines = preg_split("/\R/", $pluginsTxt);

        if ($pluginLines === false) {
            return;
        }

        foreach ($pluginLines as $pluginLine) {
            preg_match(
                '/^(?<pluginKey>[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*)(@(?<version>\S+))?\s*$/',
                $pluginLine,
                $matches,
            );

            if (array_key_exists('pluginKey', $matches)) {
                $this->plugins[$matches['pluginKey']] = [
                    'constraint'=> $matches['version'] ?? null
                ];
            }
        }
    }
}
