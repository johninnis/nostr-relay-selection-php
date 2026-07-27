<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

use Innis\Nostr\RelaySelection\Domain\Enum\Route\PublishBranch;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class PublishRoute
{
    /**
     * @param ?list<RelayUrl> $relays
     */
    public function __construct(
        private PublishBranch $branch,
        private ?array $relays,
    ) {
    }

    public function getBranch(): PublishBranch
    {
        return $this->branch;
    }

    /**
     * @return ?list<RelayUrl>
     */
    public function getRelays(): ?array
    {
        return $this->relays;
    }
}
