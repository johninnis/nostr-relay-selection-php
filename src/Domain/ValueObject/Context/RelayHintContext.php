<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class RelayHintContext
{
    public function __construct(
        private PublicKey $targetPubkey,
        private PublicKey $userPubkey,
        private array $relayListEvents,
        private array $blockedRelays = [],
    ) {
    }

    public function getTargetPubkey(): PublicKey
    {
        return $this->targetPubkey;
    }

    public function getUserPubkey(): PublicKey
    {
        return $this->userPubkey;
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
