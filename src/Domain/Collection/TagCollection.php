<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use Override;

/**
 * @extends TypedCollection<Tag>
 */
final readonly class TagCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Tag::class;
    }
}
