<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

use Castopod\PluginsManager\Logger\PluginsManagerLogger;

class JsonFile
{
    /**
     * @var array<string,array{constraint:string|null}>
     */
    public private(set) array $plugins = [];

    public private(set) bool $hasChanged = false;

    /**
     * // TODO
     *
     * @var array{type:'git',url:string,manifest_root:string}[]
     */
    public private(set) array $repositories = [];

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

    public function addPlugin(string $pluginKey, string $constraint): void
    {
        if (array_key_exists($pluginKey, $this->plugins)) {
            return;
        }

        $this->plugins[$pluginKey] = [
            'constraint' => $constraint,
        ];

        $this->hasChanged = true;

        PluginsManagerLogger::info('jsonFile.pluginAdded', 'Added plugin to plugins.json file.', [
            'pluginKey'  => $pluginKey,
            'constraint' => $constraint,
        ]);
    }

    public function removePlugin(string $pluginKey): void
    {
        if (! array_key_exists($pluginKey, $this->plugins)) {
            return;
        }

        unset($this->plugins[$pluginKey]);

        $this->hasChanged = true;

        PluginsManagerLogger::info('jsonFile.pluginRemoved', 'Removed plugin from plugins.json file.', [
            'pluginKey' => $pluginKey,
        ]);
    }

    public function write(): void
    {
        PluginsManagerLogger::info('jsonFile.writeStart', 'Writing to plugins.json file.');

        $plugins = [];
        foreach ($this->plugins as $pluginKey => $version) {
            $plugins[$pluginKey] = $version['constraint'];
        }

        // sort plugins by key to ease comparison
        ksort($plugins);

        $result = file_put_contents($this->filePath, json_encode([
            'plugins'      => $plugins,
            'repositories' => $this->repositories,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        if ($result === false) {
            PluginsManagerLogger::error('jsonFile.writeError', 'Could not write plugins.json file.');
            return;
        }

        PluginsManagerLogger::info('jsonFile.writeEnd', 'Finished writing to plugins.json file');
    }

    private function read(): void
    {
        PluginsManagerLogger::info('jsonFile.readStart', 'Reading plugins.json file.');

        if (! file_exists($this->filePath)) {
            PluginsManagerLogger::warning('jsonFile.readFileNotFound', 'plugins.json file was not found.');

            return;
        }

        $pluginsJsonString = (string) file_get_contents($this->filePath);

        if ($pluginsJsonString === '') {
            PluginsManagerLogger::warning('jsonFile.readEmptyFile', 'plugins.json file is empty.');

            return;
        }

        /** @var array<mixed> $pluginsJson */
        $pluginsJson = json_decode($pluginsJsonString, true) ?? [];

        if (array_key_exists('plugins', $pluginsJson)) {
            foreach ($pluginsJson['plugins'] as $pluginKey => $constraint) {
                $this->plugins[$pluginKey] = [
                    'constraint' => $constraint,
                ];
            }
        }

        PluginsManagerLogger::info('jsonFile.readEnd', 'Finished reading plugins.json file.');
    }
}
