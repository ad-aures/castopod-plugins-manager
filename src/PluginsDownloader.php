<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Castopod\PluginsManager\Logger\PluginsManagerLogger;

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
        PluginsManagerLogger::info('download.start', 'Downloading plugin from repository', [
            'pluginKey'  => $pluginKey,
            'repository' => $repositoryUrl,
            'subfolder'  => $subfolder,
            'commitHash' => $commitHash,
        ]);

        // create temp folder where repo is to be cloned
        $tempDirPath = tempdir('castopod-plugin_', $this->tempDirectory);

        if (! $tempDirPath) {
            PluginsManagerLogger::error('download.tempDirError', 'Couldn\'t create temp directory for plugin.');
            return false;
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
            PluginsManagerLogger::error(
                'download.defaultBranchError',
                'Download failed. Could not get default branch.',
            );
            return false;
        }

        // clean default branch output
        $defaultBranch = $defaultBranch[0];

        // pull from origin / default branch
        $this->runCommand(sprintf('cd %s && git pull origin %s', $tempDirPath, $defaultBranch));

        // switch to the commit hash / version to download
        $this->runCommand(sprintf('cd %s && git switch --detach %s', $tempDirPath, $commitHash));

        PluginsManagerLogger::success('download.end', 'Plugin downloaded.', [
            'pluginKey'         => $pluginKey,
            'repository'        => $repositoryUrl,
            'subfolder'         => $subfolder,
            'commitHash'        => $commitHash,
            'tempPluginDirPath' => $tempPluginDirPath,
        ]);

        return true;
    }

    public function copyTempPluginToDestination(string $destination): void
    {
        foreach ($this->tempPluginPaths as $pluginKey => $tempPluginPath) {
            PluginsManagerLogger::info('copyTempPluginToDestination.start', 'Copying plugin to destination', [
                'pluginKey'      => $pluginKey,
                'tempPluginPath' => $tempPluginPath,
                'destination'    => $destination,
            ]);

            $pluginDir = $destination . DIRECTORY_SEPARATOR . $pluginKey;

            // make sure plugin folder exists
            if (! is_dir($pluginDir)) {
                mkdir($pluginDir, 0777, true);
            }

            if (! xcopy($tempPluginPath, $pluginDir)) {
                PluginsManagerLogger::error(
                    'copyTempPluginToDestination.copyError',
                    'Could not copy plugin to destination.',
                    [
                        'pluginKey'      => $pluginKey,
                        'tempPluginPath' => $tempPluginPath,
                        'destination'    => $destination,

                    ],
                );

                continue;
            }

            PluginsManagerLogger::success('copyTempPluginToDestination.end', 'Plugin copied to destination', [
                'pluginKey'      => $pluginKey,
                'tempPluginPath' => $tempPluginPath,
                'destination'    => $destination,
            ]);
        }
    }

    /**
     * Delete all temporary plugin directories
     */
    public function removeTempFolders(): void
    {
        foreach ($this->tempDirPaths as $tempDirPath) {
            PluginsManagerLogger::info('removeTempFolder.start', 'Removing temp directory', [
                'tempDirPath' => $tempDirPath,
            ]);

            if (! removeDir($tempDirPath)) {
                PluginsManagerLogger::error('removeTempFolder.removeDirError', 'Could not remove temp directory.', [
                    'tempDirPath' => $tempDirPath,
                ]);
            }

            PluginsManagerLogger::success('removeTempFolder.end', 'Removed temp directory.', [
                'tempDirPath' => $tempDirPath,
            ]);
        }
    }

    /**
     * @return array<string> output
     */
    private function runCommand(string $command, bool $hideOutput = true): array
    {
        if ($hideOutput) {
            $command .= ' 2>/dev/null';
        }

        exec($command, $output);

        return $output;
    }
}
