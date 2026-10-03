<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use Override;

/**
 * @extends TypedCollection<Filter>
 */
final readonly class FilterCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Filter::class;
    }
}
