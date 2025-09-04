<?php

declare(strict_types=1);

namespace AdAures\CastopodPluginsManager\Entities;

readonly class Author
{
    public function __construct(
        public private(set) string $name,
        public private(set) ?string $email,
        public private(set) ?string $url,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromJson(array $data): self
    {
        return new self($data['name'], $data['email'], $data['url']);
    }
}
