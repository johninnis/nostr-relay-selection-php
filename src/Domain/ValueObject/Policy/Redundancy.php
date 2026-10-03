<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Policy;

final readonly class Redundancy
{
    public const int DEFAULT = 3;

    private PositiveCount $relaysPerAuthor;

    public function __construct(int $relaysPerAuthor)
    {
        $this->relaysPerAuthor = new PositiveCount($relaysPerAuthor);
    }

    public static function all(): self
    {
        return new self(PHP_INT_MAX);
    }

    public function isReachedAt(int $coverage): bool
    {
        return $coverage >= $this->relaysPerAuthor->getValue();
    }
}
