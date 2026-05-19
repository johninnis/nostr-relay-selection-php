<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

final readonly class AuthorReadRoute
{
    public function __construct(
        private array $relays,
        private array $authorChunks,
    ) {
    }

    public function getRelays(): array
    {
        return $this->relays;
    }

    public function getAuthorChunks(): array
    {
        return $this->authorChunks;
    }
}
