<?php

declare(strict_types=1);

namespace AdAures\CastopodPluginsManager\Entities;

use DateTimeImmutable;

readonly class Plugin
{
    /**
     * @param list<string> $categories
     * @param Author[] $authors
     */
    public function __construct(
        public private(set) string $key,
        public private(set) string $vendor,
        public private(set) string $name,
        public private(set) string $description,
        public private(set) string $repository_url,
        public private(set) string $manifest_root,
        public private(set) ?string $homepage_url,
        public private(set) array $categories,
        public private(set) array $authors,
        public private(set) DateTimeImmutable $created_at,
        public private(set) DateTimeImmutable $updated_at,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromJson(array $data): self
    {
        $authors = array_map(fn (array $author) => Author::fromJson($author), $data['authors']);

        return new self(
            $data['key'],
            $data['vendor'],
            $data['name'],
            $data['description'],
            $data['repository_url'],
            $data['manifest_root'],
            $data['homepage_url'],
            $data['categories'],
            $authors,
            new DateTimeImmutable($data['created_at']),
            new DateTimeImmutable($data['updated_at']),
        );
    }
}
