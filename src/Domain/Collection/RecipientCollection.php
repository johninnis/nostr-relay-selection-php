<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\Recipient;
use Override;

/**
 * @extends TypedCollection<Recipient>
 */
final readonly class RecipientCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Recipient::class;
    }
}
