<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

use DateTimeImmutable;
use Stringable;

readonly class Version implements Stringable
{
    /**
     * @param list<string> $hooks
     */
    public function __construct(
        public private(set) Plugin $plugin,
        public private(set) string $tag,
        public private(set) string $commit_hash,
        public private(set) string $readme,
        public private(set) string $license,
        public private(set) string $min_castopod_version,
        public private(set) array $hooks,
        public private(set) int $size,
        public private(set) int $file_count,
        public private(set) string $archive_url,
        public private(set) string $archive_checksum,
        public private(set) DateTimeImmutable $published_at,
    ) {
    }

    public function __toString(): string
    {
        return $this->tag;
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromJson(array $data): self
    {
        $plugin = is_array($data['plugin']) ? Plugin::fromJson($data['plugin']) : $data['plugin'];

        return new self(
            $plugin,
            $data['tag'],
            $data['commit_hash'],
            $data['readme'],
            $data['license'],
            $data['min_castopod_version'],
            $data['hooks'],
            $data['size'],
            $data['file_count'],
            $data['archive_url'],
            $data['archive_checksum'],
            new DateTimeImmutable($data['published_at']),
        );
    }
}
