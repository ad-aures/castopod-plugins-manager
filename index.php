<?php

/**
 * File to test things out
 */

declare(strict_types=1);

use Castopod\PluginsManager\PluginsManager;

// use Castopod\PluginsManager\PluginsManager;
// use z4kn4fein\SemVer\Constraints\Constraint as SemverConstraint;
// use z4kn4fein\SemVer\Version as Semver;

require_once __DIR__ . '/vendor/autoload.php';
// require_once __DIR__ . '/PluginsRepositoryClient.php';
// require_once __DIR__ . '/PluginsDownloader.php';
// require_once __DIR__ . '/PluginsManager.php';
// require_once __DIR__ . '/Entities/Version.php';
// require_once __DIR__ . '/Entities/Plugin.php';
// require_once __DIR__ . '/Entities/Author.php';
// require_once __DIR__ . '/helpers.php';

define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);

var_dump(ROOT_PATH);

$pluginsManager = new PluginsManager('http://localhost:8080/', '.', ROOT_PATH . 'plugins', ROOT_PATH . 'temp');
// $pluginsManager->installFromPluginsTxt('./plugins.txt');
$pluginsManager->add('ad-aures/custom-head');
$pluginsManager->writeMetadata();

// $pluginsRepositoryClient = new PluginsRepositoryClient('http://localhost:8080/');

// $version = $pluginsRepositoryClient->getVersion('ad-aures/podcast-license', 'azdd');


// $pluginsDownloader = new PluginsDownloader('./plugins', $version->plugin->manifest_root);
// $pluginsDownloader->download(
//     $version->plugin->key,
//     $version->plugin->repository_url,
//     $version->plugin->manifest_root,
//     $version->commit_hash,
// );
