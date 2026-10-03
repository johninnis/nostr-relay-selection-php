<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Override;

/**
 * @extends TypedCollection<Event>
 */
final readonly class EventCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Event::class;
    }
}
