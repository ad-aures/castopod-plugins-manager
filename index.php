<?php

/**
 * File to test things out
 */

declare(strict_types=1);

use Castopod\PluginsManager\PluginsManager;

require_once __DIR__ . '/vendor/autoload.php';

define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
// define('CPM_VERSION', 'dev-main');

// TODO
$cpm = new PluginsManager('https://da326c8c287f.ngrok-free.app/', ROOT_PATH, ROOT_PATH . 'plugins', ROOT_PATH . 'temp');

$cpm->add('ad-aures/podcast-license');
