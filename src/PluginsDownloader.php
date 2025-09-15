<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Exception;

class PluginsDownloader
{
    /**
     * @var list<string>
     */
    public private(set) array $tempDirPaths = [];

    /**
     * @var array<string,string>
     */
    public private(set) array $tempPluginPaths = [];

    public function __construct(
        public ?string $tempDirectory = null,
    ) {
    }

    public function download(
        string $pluginKey,
        string $repositoryUrl,
        string $subfolder,
        string $commitHash,
    ): bool {
        // create temp folder where repo is to be cloned
        $tempDirPath = tempdir('castopod-plugin_', $this->tempDirectory);

        if (! $tempDirPath) {
            throw new Exception(sprintf('Couldn\'t create temp directory for plugin %s', $pluginKey));
        }

        $tempPluginDirPath = sprintf('%s%s', $tempDirPath, $subfolder === '' ? '' : '/' . $subfolder);

        // add tempDirPath
        $this->tempPluginPaths[$pluginKey] = $tempPluginDirPath;
        $this->tempDirPaths[] = $tempDirPath;

        // clone repository
        $this->runCommand(sprintf('cd %s && git init', $tempDirPath));
        $this->runCommand(sprintf('cd %s && git remote add -f origin %s', $tempDirPath, $repositoryUrl));

        // do a sparse checkout to get subfolder
        if ($subfolder !== '') {
            $this->runCommand(sprintf('cd %s && git config core.sparseCheckout true', $tempDirPath));
            $this->runCommand(sprintf('cd %s && echo "%s" >> .git/info/sparse-checkout', $tempDirPath, $subfolder));
        }

        // get default branch
        $defaultBranch = $this->runCommand(sprintf('cd %s && git branch --show-current', $tempDirPath), false);


        if ($defaultBranch === []) {
            throw new Exception('Could not get default branch.');
        }

        var_dump('HELLO');

        // clean default branch output
        $defaultBranch = $defaultBranch[0];

        // pull from origin / default branch
        $this->runCommand(sprintf('cd %s && git pull origin %s', $tempDirPath, $defaultBranch));

        // switch to the commit hash / version to download
        $this->runCommand(sprintf('cd %s && git switch --detach %s', $tempDirPath, $commitHash));

        return true;
    }

    public function copyToDestination(string $destination): void
    {
        foreach ($this->tempPluginPaths as $pluginKey => $tempPluginPath) {
            $pluginDir = $destination . DIRECTORY_SEPARATOR . $pluginKey;
            
            // make sure plugin folder exists
            if (! is_dir($pluginDir)) {
                mkdir($pluginDir, 0777, true);
            }

            xcopy($tempPluginPath, $pluginDir);
        }
    }

    /**
     * Delete all temporary plugin directories
     */
    public function removeTempFolders(): void
    {
        foreach ($this->tempDirPaths as $tempDirPath) {
            removeDir($tempDirPath);
        }
    }

    /**
     * @return array<string> output
     */
    private function runCommand(string $command, bool $hideOutput = false): array
    {
        if ($hideOutput) {
            $command = ' 2>/dev/null';
        }

        exec($command, $output);

        return $output;
    }
}
