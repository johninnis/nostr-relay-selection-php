<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;

final readonly class ReadRoute
{
    public function __construct(
        private ReadBranch $branch,
        private ?array $relays,
    ) {
    }

    public function getBranch(): ReadBranch
    {
        return $this->branch;
    }

    public function getRelays(): ?array
    {
        return $this->relays;
    }
}
