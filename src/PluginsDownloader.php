<?php

declare(strict_types=1);

class PluginsDownloader
{
    /**
     * @var list<string>
     */
    public private(set) array $tempDirPaths = [];

    public function __construct(
        public private(set) string $pluginsFolder,
        public ?string $tempDirectory = null,
    ) {
    }

    public function download(
        string $pluginKey,
        string $repositoryUrl,
        string $subfolder,
        string $commitHash,
        bool $copyToPluginFolder = true,
    ): void {
        // create temp folder where repo is to be cloned
        $tempDirPath = tempdir('castopod-plugin_', $this->tempDirectory);

        if (! $tempDirPath) {
            throw new Exception(sprintf('Couldn\'t create temp directory for plugin %s', $pluginKey));
        }

        $tempPluginDirPath = sprintf('%s%s', $tempDirPath, $subfolder === '' ? '' : '/' . $subfolder);

        // add tempDirPath to
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
        $defaultBranch = $this->runCommand(sprintf('cd %s && git branch --show-current', $tempDirPath), true);

        if ($defaultBranch === []) {
            throw new Exception('Could not get default branch.');
        }

        // clean default branch output
        $defaultBranch = $defaultBranch[0];

        // pull from origin / default branch
        $this->runCommand(sprintf('cd %s && git pull origin %s', $tempDirPath, $defaultBranch));

        // switch to the commit hash / version to download
        $this->runCommand(sprintf('cd %s && git switch --detach %s', $tempDirPath, $commitHash));

        // first make sure target folder exists
        $pluginFolder = sprintf('%s/%s', $this->pluginsFolder, $pluginKey);
        if (! file_exists($pluginFolder)) {
            mkdir($pluginFolder, 0777, true);
        }

        if ($copyToPluginFolder) {
            // copy temp plugin folder to final plugin folder
            xcopy($tempPluginDirPath, $pluginFolder);
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
    private function runCommand(string $command, bool $hideOutput = true): array
    {
        if ($hideOutput) {
            $command = ' 2>/dev/null';
        }

        exec($command, $output);

        return $output;
    }
}
