<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Override;

/**
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 */
abstract readonly class TypedCollection implements IteratorAggregate, Countable
{
    /** @var list<T> */
    protected array $items;

    /**
     * @param array<array-key, mixed> $items
     */
    final public function __construct(array $items = [])
    {
        $type = $this->elementType();
        $this->items = array_values(array_map(
            static fn (mixed $item): object => $item instanceof $type
                ? $item
                : throw new InvalidArgumentException(sprintf('%s accepts only %s elements, got %s', static::class, $type, get_debug_type($item))),
            $items,
        ));
    }

    /**
     * @return class-string<T>
     */
    abstract protected function elementType(): string;

    final public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    #[Override]
    final public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, T>
     */
    #[Override]
    final public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<T>
     */
    final public function toArray(): array
    {
        return $this->items;
    }
}
