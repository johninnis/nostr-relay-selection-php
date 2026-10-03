<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Override;

/**
 * @extends TypedCollection<PublicKey>
 */
final readonly class PublicKeyCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return PublicKey::class;
    }
}
