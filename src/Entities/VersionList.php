<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

readonly class VersionList
{
    /**
     * @param list<string> $all_tags
     */
    public function __construct(
        public private(set) string|Plugin $plugin,
        public private(set) string $latest,
        public private(set) array $all_tags,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromJson(array $data): self
    {
        $plugin = is_array($data['plugin']) ? Plugin::fromJson($data['plugin']) : $data['plugin'];

        return new self($plugin, $data['latest'], $data['all_tags']);
    }
}
