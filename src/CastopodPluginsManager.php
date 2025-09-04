<?php

declare(strict_types=1);

namespace AdAures\CastopodPluginsManager;

use PluginsDownloader;

class CastopodPluginsManager
{
    protected PluginsRepositoryClient $pluginsRepositoryClient;

    public function __construct(
        string $repositoryUrl,
        public private(set) string $pluginsFolder,
        public private(set) ?string $tempFolder = null,
    ) {
        $this->pluginsRepositoryClient = new PluginsRepositoryClient($repositoryUrl);
    }

    public function add(string $pluginKey, ?string $pluginVersion = null): void
    {
        // get info from plugins repository
        $version = $this->pluginsRepositoryClient->getInfo($pluginKey, $pluginVersion);

        // download plugin
        $pluginsDownloader = new PluginsDownloader($this->pluginsFolder, $this->tempFolder);

        $pluginsDownloader->download(
            $pluginKey,
            $version->plugin->repository_url,
            $version->plugin->manifest_root,
            $version->commit_hash,
        );

        $pluginsDownloader->removeTempFolders();

        // trigger download increment to API
        $this->pluginsRepositoryClient->incrementDownload($pluginKey, $version->tag);
    }

    public function update(string $pluginVendor, string $pluginName, ?string $pluginVersion = null): void
    {
        // TODO

        // check that plugin is already installed

    }

    public function remove(string $pluginKey): void
    {
        // TODO
    }
}
