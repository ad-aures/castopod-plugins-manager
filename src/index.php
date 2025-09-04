<?php

declare(strict_types=1);

require_once __DIR__ . '/src/PluginsRepositoryClient.php';
require_once __DIR__ . '/src/PluginDownloader.php';
require_once __DIR__ . '/src/Entities/Version.php';
require_once __DIR__ . '/src/Entities/Plugin.php';
require_once __DIR__ . '/src/Entities/Author.php';
require_once __DIR__ . '/src/helpers.php';

use AdAures\CastopodPluginsManager\PluginsRepositoryClient;

$pluginsRepositoryClient = new PluginsRepositoryClient('http://localhost:8080/');

$version = $pluginsRepositoryClient->getInfo('ad-aures/podcast-license');

var_dump($version);

$pluginsDownloader = new PluginsDownloader('./plugins', $version->plugin->manifest_root);
$pluginsDownloader->download(
    $version->plugin->key,
    $version->plugin->repository_url,
    $version->plugin->manifest_root,
    $version->commit_hash,
);
