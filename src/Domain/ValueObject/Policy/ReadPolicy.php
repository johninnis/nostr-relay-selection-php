<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Policy;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class ReadPolicy
{
    public function __construct(
        private PublicKey $userPubkey,
        private RelaySet $callerRelays = new RelaySet(),
    ) {
    }

    public function getUserPubkey(): PublicKey
    {
        return $this->userPubkey;
    }

    public function getCallerRelays(): RelaySet
    {
        return $this->callerRelays;
    }
}
