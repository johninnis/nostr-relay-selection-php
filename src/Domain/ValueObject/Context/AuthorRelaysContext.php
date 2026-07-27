<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class AuthorRelaysContext
{
    /**
     * @param list<Event>    $relayListEvents
     * @param list<RelayUrl> $blockedRelays
     */
    public function __construct(
        private PublicKey $authorPubkey,
        private array $relayListEvents,
        private array $blockedRelays = [],
    ) {
    }

    public function getAuthorPubkey(): PublicKey
    {
        return $this->authorPubkey;
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
}
