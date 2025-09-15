<?php

declare(strict_types=1);

namespace Castopod\PluginsManager;

use Castopod\PluginsManager\Entities\Version;
use Castopod\PluginsManager\Entities\VersionList;
use Error;
use Exception;

class PluginsRepositoryClient
{
    protected string $apiBaseURL;

    public function __construct(string $endpoint, string $version = '1')
    {
        $this->apiBaseURL = sprintf('%s/api/v%s', rtrim($endpoint, '/'), $version);
    }

    public function getVersion(string $pluginKey, ?string $pluginVersion = null): Version
    {
        $versionData = $this->get(sprintf('/%s/v/%s?expand[]=plugin', $pluginKey, $pluginVersion ?? 'latest'));

        if ($versionData === false) {
            throw new Exception(sprintf('Could not find version "%s" for plugin "%s"', $pluginVersion, $pluginKey));
        }

        return Version::fromJson($versionData);
    }

    public function getVersionList(string $pluginKey): VersionList
    {
        $versionListData = $this->get(sprintf('/%s/versions', $pluginKey));

        if ($versionListData === false) {
            throw new Exception(sprintf('Could not get version list for plugin %s', $pluginKey));
        }

        return VersionList::fromJson($versionListData);
    }

    public function incrementDownload(string $pluginKey, string $pluginVersion): bool
    {
        $ch = curl_init($this->apiBaseURL . sprintf('/%s/v/%s/downloads', $pluginKey, $pluginVersion));

        curl_setopt($ch, CURLOPT_POST, true);

        /** @var bool $response */
        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function get(string $route): array|false
    {
        $url = sprintf('%s/%s', $this->apiBaseURL, ltrim($route, '/'));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        // return the response from the server as a string instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            /** @var array<mixed> */
            return json_decode($response, true);
        }

        // handle more accurate error messages, depending on 404 / 500, etc.
        return false;
    }
}
