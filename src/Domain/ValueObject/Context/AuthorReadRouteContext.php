<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

final readonly class AuthorReadRouteContext
{
    public const DEFAULT_MAX_AUTHORS_PER_FILTER = 200;
    public const DEFAULT_REDUNDANCY = 3;

    public function __construct(
        private array $authorPubkeys,
        private array $relayListEvents,
        private array $fallbackRelays,
        private int $maxAuthorsPerFilter = self::DEFAULT_MAX_AUTHORS_PER_FILTER,
        private ?int $redundancy = self::DEFAULT_REDUNDANCY,
        private array $blockedRelays = [],
    ) {
    }

    public function getAuthorPubkeys(): array
    {
        return $this->authorPubkeys;
    }

    public function getRelayListEvents(): array
    {
        return $this->relayListEvents;
    }

    public function getFallbackRelays(): array
    {
        return $this->fallbackRelays;
    }

    public function getMaxAuthorsPerFilter(): int
    {
        return $this->maxAuthorsPerFilter;
    }

    public function getRedundancy(): ?int
    {
        return $this->redundancy;
    }

    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }
}
