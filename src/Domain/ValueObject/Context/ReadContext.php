<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Entity\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class ReadContext
{
    /**
     * @param list<RelayUrl> $userRelayUrls
     * @param list<RelayUrl> $callerRelays
     * @param list<Filter>   $filters
     * @param list<Event>    $relayListEvents
     * @param list<RelayUrl> $blockedRelays
     * @param list<RelayUrl> $searchRelays
     */
    public function __construct(
        private array $userRelayUrls,
        private array $callerRelays,
        private array $filters,
        private array $relayListEvents,
        private array $blockedRelays = [],
        private array $searchRelays = [],
    ) {
    }

    /**
     * @return list<RelayUrl>
     */
    public function getUserRelayUrls(): array
    {
        return $this->userRelayUrls;
    }

    /**
     * @return list<RelayUrl>
     */
    public function getCallerRelays(): array
    {
        return $this->callerRelays;
    }

    /**
     * @return list<Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
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
    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }

    /**
     * @return list<RelayUrl>
     */
    public function getSearchRelays(): array
    {
        return $this->searchRelays;
    }
}
