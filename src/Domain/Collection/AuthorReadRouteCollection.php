<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\AuthorReadRoute;
use Override;

/**
 * @extends TypedCollection<AuthorReadRoute>
 */
final readonly class AuthorReadRouteCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return AuthorReadRoute::class;
    }
}
