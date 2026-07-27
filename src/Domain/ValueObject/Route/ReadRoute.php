<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class ReadRoute
{
    /**
     * @param ?list<RelayUrl> $relays
     */
    public function __construct(
        private ReadBranch $branch,
        private ?array $relays,
    ) {
    }

    public function getBranch(): ReadBranch
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
