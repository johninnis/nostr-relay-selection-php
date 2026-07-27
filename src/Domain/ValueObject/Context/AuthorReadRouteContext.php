<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class AuthorReadRouteContext
{
    public const int DEFAULT_MAX_AUTHORS_PER_FILTER = 200;
    public const int DEFAULT_REDUNDANCY = 3;

    /**
     * @param list<PublicKey> $authorPubkeys
     * @param list<Event>     $relayListEvents
     * @param list<RelayUrl>  $fallbackRelays
     * @param list<RelayUrl>  $blockedRelays
     */
    public function __construct(
        private array $authorPubkeys,
        private array $relayListEvents,
        private array $fallbackRelays,
        private int $maxAuthorsPerFilter = self::DEFAULT_MAX_AUTHORS_PER_FILTER,
        private ?int $redundancy = self::DEFAULT_REDUNDANCY,
        private array $blockedRelays = [],
    ) {
    }

    /**
     * @return list<PublicKey>
     */
    public function getAuthorPubkeys(): array
    {
        return $this->authorPubkeys;
    }

    /**
     * @return list<Event>
     */
    public function getRelayListEvents(): array
    {
        return $this->relayListEvents;
    }

    /**
     * @return list<RelayUrl>
     */
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

    /**
     * @return list<RelayUrl>
     */
    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }
}
