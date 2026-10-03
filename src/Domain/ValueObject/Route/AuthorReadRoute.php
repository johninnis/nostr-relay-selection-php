<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use InvalidArgumentException;

final readonly class AuthorReadRoute
{
    /**
     * @param list<PublicKeyCollection> $authorChunks
     */
    public function __construct(
        private RelaySet $relays,
        private array $authorChunks,
    ) {
        if ($relays->isEmpty()) {
            throw new InvalidArgumentException('An author read route must name at least one relay');
        }
    }

    public function getRelays(): RelaySet
    {
        return $this->relays;
    }

    /**
     * @return list<PublicKeyCollection>
     */
    public function getAuthorChunks(): array
    {
        return $this->authorChunks;
    }
}
