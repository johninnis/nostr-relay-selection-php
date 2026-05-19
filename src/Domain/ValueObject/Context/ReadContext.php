<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

final readonly class ReadContext
{
    public function __construct(
        private array $userRelayUrls,
        private array $callerRelays,
        private array $filters,
        private array $relayListEvents,
        private array $blockedRelays = [],
        private array $searchRelays = [],
    ) {
    }

    public function getUserRelayUrls(): array
    {
        return $this->userRelayUrls;
    }

    public function getCallerRelays(): array
    {
        return $this->callerRelays;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getRelayListEvents(): array
    {
        return $this->relayListEvents;
    }

    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }

    public function getSearchRelays(): array
    {
        return $this->searchRelays;
    }
}
