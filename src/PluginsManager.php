<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Castopod\PluginsManager\Entities\Lockfile;
use Castopod\PluginsManager\Entities\LockfilePlugin;
use Castopod\PluginsManager\Entities\TxtFile;
use Castopod\PluginsManager\Entities\Version;
use Exception;
use z4kn4fein\SemVer\Constraints\Constraint as SemverConstraint;
use z4kn4fein\SemVer\Version as Semver;

class PluginsManager
{
    public private(set) TxtFile $pluginsTxtFile;

    public private(set) Lockfile $pluginsLockfile;

    public private(set) string $pluginsDirPath;

    public private(set) ?string $tempDirPath = null;

    protected PluginsRepositoryClient $pluginsRepositoryClient;

    public function __construct(
        string $repositoryUrl,
        string $pluginsTxtRoot,
        string $pluginsDirPath,
        ?string $tempDirPath = null,
    ) {
        $pluginsTxtRoot = rtrim($pluginsTxtRoot, '/');

        $this->pluginsTxtFile = new TxtFile($pluginsTxtRoot . DIRECTORY_SEPARATOR . 'plugins.txt');
        $this->pluginsLockfile = new Lockfile($pluginsTxtRoot . DIRECTORY_SEPARATOR . 'plugins-lock.json');

        // first, make sure plugins and temp directories exist, create them otherwise
        $this->pluginsDirPath = rtrim($pluginsDirPath, '/');
        if (! file_exists($this->pluginsDirPath)) {
            mkdir($this->pluginsDirPath, 0777, true);
        }

        if ($tempDirPath !== null) {
            $this->tempDirPath = $tempDirPath === null ? null : rtrim($tempDirPath, '/');

            if (! file_exists($this->tempDirPath)) {
                mkdir($this->tempDirPath, 0777, true);
            }
        }

        $this->pluginsRepositoryClient = new PluginsRepositoryClient($repositoryUrl);
    }

    /**
     * @param array<string,string|null> $plugins
     */
    public function install(array $plugins = [], bool $addToTxtFile = true): void
    {
        if ($plugins === []) {
            $this->error('Nothing to install');
        }

        foreach ($plugins as $pluginKey => $version) {
            $this->add($pluginKey, $version['constraint'], $addToTxtFile);
        }
    }

    public function installFromPluginsTxt(): void
    {
        $this->install($this->pluginsTxtFile->plugins, false);
    }

    /**
     * Installs a plugin into the plugin folder.
     *
     * @param null|string|Version $versionOrConstraint Specific version or constraint (eg. ^1.0.0)
     */
    public function add(string $pluginKey, ?string $versionOrConstraint = null, bool $addToTxtFile = true): void
    {
        $version = $versionOrConstraint;
        if (! $version instanceof Version) {
            $version = $this->getSatisfiedVersion($pluginKey, $versionOrConstraint);
        }

        // download plugin
        $pluginsDownloader = new PluginsDownloader($this->tempDirPath);

        $pluginsDownloader->download(
            $pluginKey,
            $version->plugin->repository_url,
            $version->plugin->manifest_root,
            $version->commit_hash,
        );

        $pluginsDownloader->copyToDestination($this->pluginsDirPath);
        $pluginsDownloader->removeTempFolders();

        // trigger download increment to API
        $this->pluginsRepositoryClient->incrementDownload($pluginKey, $version->tag);

        if ($addToTxtFile) {
            $this->pluginsTxtFile->addPlugin($pluginKey, $version->tag);
        }

        // add plugin to lockfile
        $this->pluginsLockfile->addPlugin($pluginKey, $version);
    }

    /**
     * Updates a plugin to the latest version
     */
    public function update(string $pluginKey): void
    {
        // grab version or constraint from pluginTxt file
        $pluginConstraint = $this->pluginsTxtFile->getPluginConstraint($pluginKey);

        if ($pluginConstraint === null) {
            throw new Exception(sprintf("Could not find plugin %s in plugin.txt file. Have you added it?", $pluginKey));
        }

        $satisfiedVersion = $this->getSatisfiedVersion($pluginKey, $pluginConstraint['constraint']);

        $lockfilePlugin = $this->pluginsLockfile->getPlugin($pluginKey);
        if ($lockfilePlugin instanceof LockfilePlugin) {
            if (
                $satisfiedVersion->tag === $lockfilePlugin->version
                && $satisfiedVersion->commit_hash === $lockfilePlugin->source['reference']
            ) {
                throw new Exception("Plugin is already up to date!");
            }
        }

        // update to latest version, remove old version first
        $this->remove($pluginKey);
        $this->add($pluginKey, $satisfiedVersion->tag, false);
    }

    public function remove(string $pluginKey): bool
    {
        // check that plugin is already installed
        $pluginFolder = $this->getPluginFolderPath($pluginKey);
        if (! file_exists($pluginFolder)) {
            // nothing do remove
            return false;
        }

        removeDir($pluginFolder);

        $this->pluginsTxtFile->removePlugin($pluginKey);
        $this->pluginsLockfile->removePlugin($pluginKey);

        return true;
    }

    public function writeMetadata(): void
    {
        $this->pluginsTxtFile->write();
        $this->pluginsLockfile->write();
    }

    private function getPluginFolderPath(string $pluginKey): string
    {
        return $this->pluginsDirPath . DIRECTORY_SEPARATOR . $pluginKey;
    }

    private function error(string $message): void
    {
        throw new Exception($message);
    }

    private function getSatisfiedVersion(string $pluginKey, ?string $versionOrConstraint = null): Version
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
            throw new Exception(sprintf('Invalid version or constraint: %s', $versionOrConstraint));
        }

        // get version list for plugin
        $versionList = $this->pluginsRepositoryClient->getVersionList($pluginKey);

        // parse all versions tags, invalid "dev-*" version will be parsed to null
        $versions = array_map(fn (string $version) => Semver::parseOrNull($version), $versionList->all_tags);

        // remove "dev-*" versions set to null during parse
        /** @var Semver[] $versions */
        $versions = array_filter($versions, static fn (?Semver $semver) => $semver !== null);

        // sort versions from highest to lowest
        $sortedVersions = Semver::rsort($versions);

        foreach ($sortedVersions as $semver) {
            if ($semver->isSatisfying($constraint)) {
                // get version from repository
                return $this->pluginsRepositoryClient->getVersion($pluginKey, (string) $semver);
            }
        }

        throw new Exception(sprintf('No version satisfies constraint %s', $versionOrConstraint));
    }
}
