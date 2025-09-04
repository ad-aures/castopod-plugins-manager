<?php

declare(strict_types=1);

namespace AdAures\CastopodPluginsManager;

use AdAures\CastopodPluginsManager\Entities\Version;
use Exception;

class PluginsRepositoryClient
{
    protected string $apiBaseURL;

    public function __construct(string $endpoint, string $version = '1')
    {
        $this->apiBaseURL = sprintf('%s/api/v%s', rtrim($endpoint, '/'), $version);
    }

    public function getInfo(string $pluginKey, ?string $pluginVersion = null): Version
    {
        $versionData = $this->get(sprintf('/%s/v/%s?expand[]=plugin', $pluginKey, $pluginVersion ?? 'latest'));

        return Version::fromJson($versionData);
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
    private function get(string $route): array
    {
        $url = sprintf('%s/%s', $this->apiBaseURL, ltrim($route, '/'));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        // return the response from the server as a string instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close($ch);

        if (curl_errno($ch) !== 0) {
            throw new Exception(curl_error($ch));
        }

        // no errors, $response is a string
        assert(is_string($response));

        /** @var array<mixed> */
        return json_decode($response, true);
    }
}
