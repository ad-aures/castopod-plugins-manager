<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Entities;

readonly class LockfilePlugin
{
    /**
     * @param array{url:string,reference:string} $source
     * @param array{url:string,checksum:string} $dist
     */
    public function __construct(
        public private(set) string $version,
        public private(set) array $source,
        public private(set) array $dist,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromJsonOrNull(array $data): ?self
    {
        if (! array_key_exists('version', $data)) {
            return null;
        }

        if (! array_key_exists('source', $data)) {
            return null;
        }

        if (! is_array($data['source'])) {
            return null;
        }

        if (
            ! array_key_exists('url', $data['source'])
            || ! array_key_exists('path', $data['source'])
            || ! array_key_exists('reference', $data['source'])
        ) {
            return null;
        }

        if (! array_key_exists('dist', $data)) {
            return null;
        }

        if (! is_array($data['dist'])) {
            return null;
        }

        if (! array_key_exists('url', $data['dist']) || ! array_key_exists('checksum', $data['dist'])) {
            return null;
        }

        return new self($data['version'], $data['source'], $data['dist']);
    }
}
