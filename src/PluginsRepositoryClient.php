<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Castopod\PluginsManager\Entities\Version;
use Castopod\PluginsManager\Entities\VersionList;
use Castopod\PluginsManager\Logger\PluginsManagerLogger;

class PluginsRepositoryClient
{
    protected string $apiBaseURL;

    public function __construct(
        protected string $endpoint,
        string $version = '1',
    ) {
        $this->apiBaseURL = sprintf('%s/api/v%s', rtrim($this->endpoint, '/'), $version);

        // do health check with the repository
        $healthCheck = $this->get('health');

        if ($healthCheck === false) {
            PluginsManagerLogger::error('repositoryClient.notResponding', 'Plugin repository is not responding.', [
                'repository' => $this->endpoint,
            ]);
        }
    }

    public function getVersion(string $pluginKey, ?string $pluginVersion = null): ?Version
    {
        $pluginVersion ??= 'latest';
        $route = sprintf('/%s/v/%s?expand[]=plugin', $pluginKey, $pluginVersion);

        PluginsManagerLogger::info('repositoryClient.getVersion.start', 'Getting version info from repository.', [
            'pluginKey'  => $pluginKey,
            'version'    => $pluginVersion,
            'repository' => $this->endpoint,
            'route'      => $route,
        ]);

        $versionData = $this->get($route);

        if ($versionData === false) {
            PluginsManagerLogger::error('repositoryClient.getVersion.notFound', 'Could not find version', [
                'pluginKey'  => $pluginKey,
                'version'    => $pluginVersion,
                'repository' => $this->endpoint,
                'route'      => $route,
            ]);
            return null;
        }

        assert(is_array($versionData));

        PluginsManagerLogger::success(
            'repositoryClient.getVersion.end',
            'Success getting Version info from repository.',
            [
                'pluginKey'  => $pluginKey,
                'version'    => $pluginVersion,
                'repository' => $this->endpoint,
                'route'      => $route,

            ],
        );

        return Version::fromJson($versionData);
    }

    public function getVersionList(string $pluginKey): ?VersionList
    {
        $route = sprintf('/%s/versions', $pluginKey);

        PluginsManagerLogger::info('repositoryClient.getVersionList.start', 'Getting Version list from repository.', [
            'pluginKey'  => $pluginKey,
            'repository' => $this->endpoint,
            'route'      => $route,
        ]);

        $versionListData = $this->get($route);

        if ($versionListData === false) {
            PluginsManagerLogger::error(
                'repositoryClient.getVersionList.notFound',
                'Could not get Version list from repository',
                [
                    'pluginKey'  => $pluginKey,
                    'repository' => $this->endpoint,
                    'route'      => $route,
                ],
            );
            return null;
        }

        assert(is_array($versionListData));

        PluginsManagerLogger::success(
            'repositoryClient.getVersionList.end',
            'Success getting Version list from repository.',
            [
                'pluginKey'  => $pluginKey,
                'repository' => $this->endpoint,
                'route'      => $route,
            ],
        );

        return VersionList::fromJson($versionListData);
    }

    public function incrementDownload(string $pluginKey, string $pluginVersion): bool
    {
        PluginsManagerLogger::info(
            'repositoryClient.incrementDownload.start',
            'Sending hit to increment download count in repository.',
            [
                'pluginKey'  => $pluginKey,
                'version'    => $pluginVersion,
                'repository' => $this->endpoint,
            ],
        );

        $ch = curl_init($this->apiBaseURL . sprintf('/%s/v/%s/downloads', $pluginKey, $pluginVersion));

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            ['User-Agent: CastopodPluginsManager/' . CPM_VERSION, 'Accept: application/json'],
        );

        /** @var bool $response */
        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            PluginsManagerLogger::error('repositoryClient.incrementDownload.error', 'Download increment failed.', [
                'pluginKey'  => $pluginKey,
                'version'    => $pluginVersion,
                'repository' => $this->endpoint,
            ]);

            return false;
        }

        PluginsManagerLogger::success(
            'repositoryClient.incrementDownload.end',
            'Hit to increment download count in repository was sent.',
            [
                'pluginKey'  => $pluginKey,
                'version'    => $pluginVersion,
                'repository' => $this->endpoint,
            ],
        );

        return true;
    }

    /**
     * @return array<mixed>|bool
     */
    private function get(string $route): array|bool
    {
        $url = sprintf('%s/%s', $this->apiBaseURL, ltrim($route, '/'));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        // return the response from the server as a string instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            ['User-Agent: CastopodPluginsManager/' . CPM_VERSION, 'Accept: application/json'],
        );

        $response = curl_exec($ch);

        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            if (! is_string($response)) {
                return true;
            }

            if ($response === '') {
                return true;
            }

            /** @var array<mixed> */
            return json_decode($response, true);
        }

        // handle more accurate error messages, depending on 404 / 500, etc.
        return false;
    }
}
