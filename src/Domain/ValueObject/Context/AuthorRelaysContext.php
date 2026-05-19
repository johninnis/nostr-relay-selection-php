<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Context;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class AuthorRelaysContext
{
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

    public function getRelayListEvents(): array
    {
        return $this->relayListEvents;
    }

    public function getBlockedRelays(): array
    {
        return $this->blockedRelays;
    }
}
