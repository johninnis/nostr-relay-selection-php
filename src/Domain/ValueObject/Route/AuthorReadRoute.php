<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class AuthorReadRoute
{
    /**
     * @param list<RelayUrl>        $relays
     * @param list<list<PublicKey>> $authorChunks
     */
    public function __construct(
        private array $relays,
        private array $authorChunks,
    ) {
    }

    /**
     * @return list<RelayUrl>
     */
    public function getRelays(): array
    {
        return $this->relays;
    }

    /**
     * @return list<list<PublicKey>>
     */
    public function getAuthorChunks(): array
    {
        return $this->authorChunks;
    }
}
