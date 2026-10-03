<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Routing;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class Recipient
{
    public function __construct(
        private PublicKey $pubkey,
        private ?RelayUrl $hint,
    ) {
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function getHint(): ?RelayUrl
    {
        return $this->hint;
    }
}
