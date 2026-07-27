<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class PublishContext
{
    public const int DEFAULT_PER_RECIPIENT_CAP = 3;

    /**
     * @param list<Event>    $relayListEvents
     * @param list<RelayUrl> $privateContentRelays
     * @param list<RelayUrl> $indexerRelays
     * @param list<RelayUrl> $blockedRelays
     */
    public function __construct(
        private PublicKey $userPubkey,
        private array $relayListEvents,
        private array $privateContentRelays,
        private array $indexerRelays,
        private int $perRecipientCap = self::DEFAULT_PER_RECIPIENT_CAP,
        private array $blockedRelays = [],
    ) {
    }

    public function getUserPubkey(): PublicKey
    {
        return $this->userPubkey;
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
    public function getPrivateContentRelays(): array
    {
        return $this->privateContentRelays;
    }

    /**
     * @return list<RelayUrl>
     */
    public function getIndexerRelays(): array
    {
        return $this->indexerRelays;
    }

    public function getPerRecipientCap(): int
    {
        return $this->perRecipientCap;
    }

    /**
     * @return list<RelayUrl>
     */
    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }
}
