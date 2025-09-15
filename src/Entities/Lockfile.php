<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

class Lockfile
{
    public private(set) string $version = '1.0';

    /**
     * @var array<string,LockfilePlugin>
     */
    public private(set) array $plugins = [];

    public function __construct(
        public private(set) string $filePath,
    ) {
    }

    public function getPlugin(string $pluginKey): ?LockfilePlugin
    {
        return $this->plugins[$pluginKey] ?? null;
    }

    public function addPlugin(string $pluginKey, Version $version): void
    {
        $this->plugins[$pluginKey] = new LockfilePlugin($version->tag, [
            'url'       => $version->plugin->repository_url,
            'reference' => $version->commit_hash,
        ]);
    }

    public function removePlugin(string $pluginKey): void
    {
        unset($this->plugins[$pluginKey]);
    }

    public function read(): void
    {
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
    }

    public function write(): int|false
    {
        return file_put_contents($this->filePath, (string) json_encode([
            'version' => $this->version,
            'plugins' => $this->plugins,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
