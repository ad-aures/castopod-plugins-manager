<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Castopod\PluginsManager\Entities\JsonFile;
use Castopod\PluginsManager\Entities\Lockfile;
use Castopod\PluginsManager\Entities\LockfilePlugin;
use Castopod\PluginsManager\Entities\Version;
use Castopod\PluginsManager\Entities\VersionList;
use Castopod\PluginsManager\Logger\PluginsManagerLogger;
use z4kn4fein\SemVer\Constraints\Constraint as SemverConstraint;
use z4kn4fein\SemVer\Version as Semver;

class PluginsManager
{
    public private(set) JsonFile $pluginsJsonFile;

    public private(set) Lockfile $pluginsLockfile;

    public private(set) string $pluginsDirPath;

    public private(set) ?string $tempDirPath = null;

    protected PluginsRepositoryClient $pluginsRepositoryClient;

    public function __construct(
        string $repositoryUrl,
        string $pluginsJsonRoot,
        string $pluginsDirPath,
        ?string $tempDirPath = null,
    ) {
        PluginsManagerLogger::info('start', 'Castopod Plugins Manager is starting.');

        $pluginsJsonRoot = rtrim($pluginsJsonRoot, '/');

        $this->pluginsJsonFile = new JsonFile($pluginsJsonRoot . DIRECTORY_SEPARATOR . 'plugins.json');
        $this->pluginsLockfile = new Lockfile($pluginsJsonRoot . DIRECTORY_SEPARATOR . 'plugins-lock.json');

        // first, make sure plugins and temp directories exist, create them otherwise
        $this->pluginsDirPath = rtrim($pluginsDirPath, '/');
        if (! file_exists($this->pluginsDirPath)) {
            PluginsManagerLogger::info('construct.createPluginsDir', 'Plugins directory does not exist, creating it.', [
                'pluginsDirPath' => $this->pluginsDirPath,
            ]);

            if (! mkdir($this->pluginsDirPath, 0777, true)) {
                PluginsManagerLogger::error('construct.createPluginsDirError', 'Could not create plugins folder.', [
                    'pluginsDirPath' => $this->pluginsDirPath,
                ]);
            } else {
                PluginsManagerLogger::success(
                    'construct.createPluginsDirSuccess',
                    'Plugins directory has been created.',
                    [
                        'pluginsDirPath' => $this->pluginsDirPath,

                    ],
                );
            }
        }

        if ($tempDirPath !== null) {
            $this->tempDirPath = rtrim($tempDirPath, '/');

            if (! file_exists($this->tempDirPath)) {
                PluginsManagerLogger::info('construct.createTempDir', 'Temp directory does not exist, creating it.', [
                    'tempDirPath' => $this->tempDirPath,
                ]);

                if (! mkdir($this->tempDirPath, 0777, true)) {
                    PluginsManagerLogger::error('construct.createTempDirError', 'Could not create temp folder.', [
                        'tempDirPath' => $this->tempDirPath,
                    ]);
                } else {
                    PluginsManagerLogger::success(
                        'construct.createTempDirSuccess',
                        'Temp directory has been created.',
                        [
                            'tempDirPath' => $this->tempDirPath,
                        ],
                    );
                }
            }
        }

        $this->pluginsRepositoryClient = new PluginsRepositoryClient($repositoryUrl);
    }

    public function __destruct()
    {
        // make sure plugins.json and plugins-lock.json files are up to date

        if ($this->pluginsJsonFile->hasChanged) {
            PluginsManagerLogger::info('destruct.saveJsonFile', 'Saving state to plugins.json file.');

            $this->pluginsJsonFile->write();

            PluginsManagerLogger::success('destruct.endSaveJsonFile', 'Saved state to plugins.json files.');
        }

        if ($this->pluginsLockfile->hasChanged) {
            PluginsManagerLogger::info('destruct.saveLockFile', 'Saving state to plugins-lock.json file.');

            $this->pluginsLockfile->write();

            PluginsManagerLogger::success('destruct.endSaveLockFile', 'Saved state to plugins-lock.json file.');
        }

        PluginsManagerLogger::info('end', 'Castopod Plugins Manager is done.');
    }

    /**
     * @param array<string,string|null> $plugins
     */
    public function install(array $plugins = [], bool $addToTxtFile = true): void
    {
        if ($plugins === []) {
            PluginsManagerLogger::warning('install.nothingToInstall', 'Nothing to install');
            return;
        }

        foreach ($plugins as $pluginKey => $version) {
            $this->add($pluginKey, $version, $addToTxtFile);
        }
    }

    public function installFromPluginsTxt(): void
    {
        $plugins = [];
        foreach ($this->pluginsJsonFile->plugins as $pluginKey => $version) {
            $plugins[$pluginKey] = $version['constraint'];
        }

        $this->install($plugins, false);
    }

    /**
     * Installs a plugin into the plugin folder.
     *
     * @param null|string|Version $versionOrConstraint Specific version or constraint (eg. ^1.0.0)
     */
    public function add(string $pluginKey, mixed $versionOrConstraint = null, bool $addToTxtFile = true): void
    {
        PluginsManagerLogger::info('add.start', 'Adding plugin.', [
            'pluginKey'  => $pluginKey,
            'constraint' => $versionOrConstraint === null ? null : (string) $versionOrConstraint,
        ]);

        if (! $versionOrConstraint instanceof Version) {
            $version = $this->getSatisfiedVersion($pluginKey, $versionOrConstraint);
        } else {
            $version = $versionOrConstraint;
        }

        if (! $version instanceof Version) {
            return;
        }

        // download plugin
        $pluginsDownloader = new PluginsDownloader($this->tempDirPath);

        $pluginsDownloader->download($version);

        $pluginsDownloader->copyTempPluginToDestination($this->pluginsDirPath);
        $pluginsDownloader->clearTemp();

        // trigger download increment to API
        $this->pluginsRepositoryClient->incrementDownload($pluginKey, $version->tag);

        if ($addToTxtFile) {
            $this->pluginsJsonFile->addPlugin($pluginKey, $version->tag);
        }

        // add plugin to lockfile
        $this->pluginsLockfile->addPlugin($pluginKey, $version);

        PluginsManagerLogger::success('add.end', 'Plugin installed.', [
            'pluginKey' => $pluginKey,
            'version'   => (string) $version,
        ]);
    }

    /**
     * Updates a plugin to the latest version
     */
    public function update(string $pluginKey): void
    {
        PluginsManagerLogger::info('update.start', 'Updating plugin.', [
            'pluginKey' => $pluginKey,
        ]);

        // grab version or constraint from pluginTxt file
        $pluginConstraint = $this->pluginsJsonFile->getPluginConstraint($pluginKey);

        if ($pluginConstraint === null) {
            PluginsManagerLogger::error(
                'update.pluginNotFound',
                sprintf('Could not find plugin in %s file. Have you added it?', $this->pluginsJsonFile->filePath),
                [
                    'pluginKey' => $pluginKey,

                ],
            );
            return;
        }

        $satisfiedVersion = $this->getSatisfiedVersion($pluginKey, $pluginConstraint['constraint']);

        if (! $satisfiedVersion instanceof Version) {
            return;
        }

        $lockfilePlugin = $this->pluginsLockfile->getPlugin($pluginKey);
        if (
            $lockfilePlugin instanceof LockfilePlugin
            && (
                $satisfiedVersion->tag === $lockfilePlugin->version
                && $satisfiedVersion->commit_hash === $lockfilePlugin->source['reference']
            )
        ) {
            PluginsManagerLogger::warning('update.alreadyUpToDate', 'Plugin is already up to date.', [
                'pluginKey' => $pluginKey,
            ]);
            return;
        }

        // update to latest version, remove old version first
        $this->remove($pluginKey);
        $this->add($pluginKey, $satisfiedVersion->tag, false);

        PluginsManagerLogger::success('update.end', 'Plugin was updated.', [
            'pluginKey' => $pluginKey,
            'version'   => $satisfiedVersion->tag,
        ]);
    }

    public function remove(string $pluginKey): void
    {
        PluginsManagerLogger::info('remove.start', 'Removing plugin.', [
            'pluginKey' => $pluginKey,
        ]);

        // check that plugin is already installed
        $pluginDir = $this->pluginsDirPath . DIRECTORY_SEPARATOR . $pluginKey;
        if (! file_exists($pluginDir)) {
            PluginsManagerLogger::warning('remove.nothingToRemove', 'Nothing to remove.', [
                'pluginKey' => $pluginKey,
            ]);
            return;
        }

        if (! removeDir($pluginDir)) {
            PluginsManagerLogger::error('remove.removeDirError', 'Could not remove plugin directory.', [
                'pluginKey' => $pluginKey,
                'pluginDir' => $pluginDir,
            ]);
        }

        $this->pluginsJsonFile->removePlugin($pluginKey);
        $this->pluginsLockfile->removePlugin($pluginKey);

        PluginsManagerLogger::success('remove.end', 'Plugin removed.', [
            'pluginKey' => $pluginKey,
        ]);
    }

    private function getSatisfiedVersion(string $pluginKey, ?string $versionOrConstraint = null): ?Version
    {
        if (
            $versionOrConstraint === null
            || $versionOrConstraint === 'latest'
            || str_starts_with($versionOrConstraint, 'dev-')
        ) {
            return $this->pluginsRepositoryClient->getVersion($pluginKey, $versionOrConstraint);
        }

        $constraint = SemverConstraint::parseOrNull($versionOrConstraint);

        if (! $constraint instanceof SemverConstraint) {
            PluginsManagerLogger::error('getSatisfiedVersion.invalidConstraint', 'Invalid version or constraint', [
                'pluginKey'           => $pluginKey,
                'versionOrConstraint' => $versionOrConstraint,
            ]);

            return null;
        }

        // get version list for plugin
        $versionList = $this->pluginsRepositoryClient->getVersionList($pluginKey);

        if (! $versionList instanceof VersionList) {
            return null;
        }

        // parse all versions tags, invalid "dev-*" version will be parsed to null
        $versions = array_map(fn (string $version) => Semver::parseOrNull($version), $versionList->all_tags);

        // remove "dev-*" versions set to null during parse
        /** @var Semver[] $versions */
        $versions = array_filter($versions, static fn (?Semver $semver) => $semver instanceof Semver);

        // sort versions from highest to lowest
        $sortedVersions = Semver::rsort($versions);

        foreach ($sortedVersions as $semver) {
            if ($semver->isSatisfying($constraint)) {
                // get version from repository
                return $this->pluginsRepositoryClient->getVersion($pluginKey, (string) $semver);
            }
        }

        PluginsManagerLogger::error('getSatisfiedVersion.noVersionForConstraint', 'No version satisfies constraint.', [
            'pluginKey'           => $pluginKey,
            'versionOrConstraint' => $versionOrConstraint,
        ]);
        return null;
    }
}
