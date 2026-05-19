<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class ZapRequestContext
{
    public function __construct(
        private PublicKey $zapperPubkey,
        private PublicKey $recipientPubkey,
        private array $relayListEvents,
        private array $blockedRelays = [],
    ) {
    }

    public function getZapperPubkey(): PublicKey
    {
        return $this->zapperPubkey;
    }

    public function getRecipientPubkey(): PublicKey
    {
        return $this->recipientPubkey;
    }

    public function getRelayListEvents(): array
    {
        return $this->relayListEvents;
    }

    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }
}
