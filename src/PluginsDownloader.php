<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Castopod\PluginsManager\Entities\Version;
use Castopod\PluginsManager\Logger\PluginsManagerLogger;
use ZipArchive;

class PluginsDownloader
{
    /**
     * @var list<string>
     */
    public private(set) array $tempPaths = [];

    /**
     * @var array<string,string>
     */
    public private(set) array $tempPluginPaths = [];

    public function __construct(
        public ?string $tempDirectory = null,
    ) {
    }

    public function download(Version $version): bool
    {
        $context = [
            'pluginKey' => $version->plugin->key,
            'version'   => $version->tag,
        ];
        PluginsManagerLogger::info('download.start', 'Downloading plugin.', $context);

        $result = $this->downloadArchive($version) ? true : $this->downloadFromGitRepository($version);

        PluginsManagerLogger::success('download.end', 'Plugin downloaded successfully!', $context);

        return $result;
    }

    public function downloadArchive(Version $version): bool
    {
        $tempPluginDir = sprintf('%s/%s', $this->tempDirectory, str_replace('/', '_', $version->plugin->key));
        $tempZipPath = $tempPluginDir . '.zip';

        $context = [
            'pluginKey'       => $version->plugin->key,
            'archiveUrl'      => $version->archive_url,
            'archiveChecksum' => $version->archive_checksum,
            'tempPluginDir'   => $tempPluginDir,
            'tempZipPath'     => $tempZipPath,
        ];

        PluginsManagerLogger::info('downloadArchive.start', 'Downloading plugin archive from repository', $context);

        $file = fopen($tempZipPath, 'w');

        if ($file === false) {
            PluginsManagerLogger::error(
                'downloadArchive.openZipError',
                'Could not open zip in temp directory.',
                $context,
            );
            return false;
        }

        $ch = curl_init();

        if ($version->archive_url === '') {
            PluginsManagerLogger::error('downloadArchive.archiveUrlEmpty', 'Provided archive URL is empty.', $context);

            return false;
        }

        curl_setopt($ch, CURLOPT_URL, $version->archive_url);

        // output directly to file
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: CastopodPluginsManager/' . CPM_VERSION]);

        curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            PluginsManagerLogger::error(
                'downloadArchive.downloadError',
                sprintf('Something happened when downloading archive: %s', curl_error($ch)),
                $context,
            );

            return false;
        }
        curl_close($ch);

        fclose($file);

        $this->tempPaths[] = $tempZipPath;

        PluginsManagerLogger::info(
            'downloadArchive.archiveDownloaded',
            'Plugin archive was successfully downloaded!',
            $context,
        );

        // extract zip archive to temp folder
        $zip = new ZipArchive();
        $result = $zip->open($tempZipPath);
        if ($result === true) {
            $zip->extractTo($tempPluginDir);
            $zip->close();

            $this->tempPaths[] = $tempPluginDir;

            PluginsManagerLogger::info('downloadArchive.archiveExtracted', 'Archive extracted successfully!', $context);
        } else {
            PluginsManagerLogger::error('downloadArchive.archiveExtractError', 'Could not extract archive.', $context);
            return false;
        }

        // check that archive is well structured
        $pluginDirPath = $tempPluginDir . '/' . $version->plugin->key;
        $context['pluginDirPath'] = $pluginDirPath;
        if (! is_dir($pluginDirPath)) {
            PluginsManagerLogger::error(
                'downloadArchive.pluginDirStructureError',
                'Downloaded archive does not contain plugin.',
                $context,
            );

            return false;
        }

        // check that checksum is valid
        [$bytesTotal,$fileCount,$checksum] = get_directory_metadata($pluginDirPath);

        $context['downloaded_archive_size'] = $bytesTotal;
        $context['downloaded_archive_fileCount'] = $fileCount;
        $context['downloaded_archive_checksum'] = $checksum;

        if ($checksum !== $version->archive_checksum) {
            PluginsManagerLogger::error(
                'downloadArchive.checksumError',
                'Downloaded archive is corrupted! Please contact repository admin.',
                $context,
            );

            return false;
        }

        $this->tempPluginPaths[$version->plugin->key] = $pluginDirPath;

        PluginsManagerLogger::success(
            'downloadArchive.end',
            'Plugin archive was downloaded and extracted successfully!',
            $context,
        );

        return true;
    }

    public function downloadFromGitRepository(Version $version): bool
    {
        $context = [
            'pluginKey'  => $version->plugin->key,
            'repository' => $version->plugin->repository_url,
            'subfolder'  => $version->plugin->manifest_root,
            'commitHash' => $version->commit_hash,
        ];

        PluginsManagerLogger::info('downloadFromGitRepository.start', 'Downloading plugin from repository.', $context);

        // create temp folder where repo is to be cloned
        $tempDirPath = tempdir('castopod-plugin_', $this->tempDirectory);
        $tempPluginDirPath = sprintf(
            '%s%s',
            $tempDirPath,
            $version->plugin->manifest_root === '' ? '' : '/' . $version->plugin->manifest_root,
        );

        if (! $tempDirPath) {
            PluginsManagerLogger::error(
                'downloadFromGitRepository.tempDirError',
                'Couldn\'t create temp directory for plugin.',
                $context,
            );
            return false;
        }

        $context['tempDirPath'] = $tempDirPath;
        $context['tempPluginDirPath'] = $tempPluginDirPath;

        // add tempDirPath
        $this->tempPluginPaths[$version->plugin->key] = $tempPluginDirPath;
        $this->tempPaths[] = $tempDirPath;

        // change directory to tempPluginPath
        if (! chdir($tempDirPath)) {
            PluginsManagerLogger::error(
                'downloadFromGitRepository.chdirError',
                'Couldn\'t change directory to temp plugin directory.',
                $context,
            );

            return false;
        }

        // clone repository
        $this->runCommand('git init');
        $this->runCommand(sprintf('git remote add -f origin %s', $version->plugin->repository_url));

        // do a sparse checkout to get subfolder
        if ($version->plugin->manifest_root !== '') {
            $this->runCommand('git config core.sparseCheckout true');
            $this->runCommand(sprintf('echo "%s" >> .git/info/sparse-checkout', $version->plugin->manifest_root));
        }

        // get default branch
        $defaultBranch = $this->runCommand('git branch --show-current', false);

        if ($defaultBranch === []) {
            PluginsManagerLogger::error(
                'downloadFromGitRepository.defaultBranchError',
                'Download failed. Could not get default branch.',
                $context,
            );
            return false;
        }

        // clean default branch output
        $defaultBranch = $defaultBranch[0];

        // pull from origin / default branch
        $this->runCommand(sprintf('git pull origin %s', $defaultBranch));

        // switch to the commit hash / version to download
        $this->runCommand(sprintf('git switch --detach %s', $version->commit_hash));

        PluginsManagerLogger::success('downloadFromGitRepository.end', 'Plugin downloaded.', $context);

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
     * Delete all temporary plugin directories and files
     */
    public function clearTemp(): void
    {
        foreach ($this->tempPaths as $tempPath) {
            $context = [
                'tempPath' => $tempPath,
            ];

            PluginsManagerLogger::info('clearTemp.start', 'Removing temp directory or file', $context);

            if (is_dir($tempPath) && removeDir($tempPath)) {
                PluginsManagerLogger::success('clearTemp.end', 'Removed temp directory.', $context);
                continue;
            }

            if (is_file($tempPath) && unlink($tempPath)) {
                PluginsManagerLogger::success('clearTemp.end', 'Removed temp file.', $context);
                continue;
            }

            PluginsManagerLogger::error(
                'clearTemp.removeError',
                'Could not removing temp directory or file.',
                $context,
            );
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
