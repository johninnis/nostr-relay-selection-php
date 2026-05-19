<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class PublishContext
{
    public const DEFAULT_PER_RECIPIENT_CAP = 3;

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

    public function getRelayListEvents(): array
    {
        return $this->relayListEvents;
    }

    public function getPrivateContentRelays(): array
    {
        return $this->privateContentRelays;
    }

    public function getIndexerRelays(): array
    {
        return $this->indexerRelays;
    }

    public function getPerRecipientCap(): int
    {
        return $this->perRecipientCap;
    }

    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }
}
