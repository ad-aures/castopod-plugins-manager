<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

use Castopod\PluginsManager\Logger\PluginsManagerLogger;

class Lockfile
{
    public private(set) string $version = '1.0';

    /**
     * @var array<string,LockfilePlugin>
     */
    public private(set) array $plugins = [];

    public private(set) bool $hasChanged = false;

    public function __construct(
        public private(set) string $filePath,
    ) {
        $this->read();
    }

    public function getPlugin(string $pluginKey): ?LockfilePlugin
    {
        return $this->plugins[$pluginKey] ?? null;
    }

    public function addPlugin(string $pluginKey, Version $version): void
    {
        $lockfilePlugin = new LockfilePlugin($version->tag, [
            'url'       => $version->plugin->repository_url,
            'reference' => $version->commit_hash,
        ]);

        if (array_key_exists($pluginKey, $this->plugins) && $this->plugins[$pluginKey] === $lockfilePlugin) {
            return;
        }

        $this->plugins[$pluginKey] = $lockfilePlugin;

        $this->hasChanged = true;

        PluginsManagerLogger::info('lockFile.pluginAdded', 'Added plugin to lockfile.', [
            'pluginKey' => $pluginKey,
            'version'   => (string) $version,
        ]);
    }

    public function removePlugin(string $pluginKey): void
    {
        if (array_key_exists($pluginKey, $this->plugins)) {
            return;
        }

        unset($this->plugins[$pluginKey]);

        $this->hasChanged = true;

        PluginsManagerLogger::info('lockFile.pluginRemoved', 'Removed plugin from lockfile.', [
            'pluginKey' => $pluginKey,
        ]);
    }

    public function read(): void
    {
        PluginsManagerLogger::info('lockFile.readStart', 'Reading plugins-lock.json file.');

        if (! file_exists($this->filePath)) {
            PluginsManagerLogger::warning('lockFile.readFileNotFound', 'plugins-lock.json file was not found.');

            return;
        }

        $lockfileContents = (string) file_get_contents($this->filePath);

        /** @var array<mixed> $lockfile */
        $lockfile = json_decode($lockfileContents, true) ?? [];

        $this->version = '1.0'; // hardcoded for now

        foreach ($lockfile['plugins'] ?? [] as $key => $value) {
            $lockfilePlugin = LockfilePlugin::fromJsonOrNull($value);

            if (! $lockfilePlugin instanceof LockfilePlugin) {
                continue;
            }

            $this->plugins[$key] = $lockfilePlugin;
        }

        PluginsManagerLogger::info('lockFile.readEnd', 'Finished reading plugins-lock.json file.');
    }

    public function write(): void
    {
        PluginsManagerLogger::info('lockFile.writeStart', 'Writing to plugins-lock.json file.');

        $result = file_put_contents($this->filePath, (string) json_encode([
            'version' => $this->version,
            'plugins' => $this->plugins,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($result === false) {
            PluginsManagerLogger::error('lockFile.writeError', 'Could not write to plugins-lock.json file.');
            return;
        }

        PluginsManagerLogger::info('lockFile.writeEnd', 'Finished writing to plugins-lock.json file.');
    }
}
