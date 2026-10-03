<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Collection;

use ArrayIterator;
use Countable;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use InvalidArgumentException;
use IteratorAggregate;
use Override;

/**
 * @implements IteratorAggregate<int, RelayUrl>
 */
final readonly class RelaySet implements IteratorAggregate, Countable
{
    /** @var array<string, RelayUrl> */
    private array $byUrl;

    /**
     * @param array<array-key, mixed> $urls
     */
    public function __construct(array $urls = [])
    {
        $byUrl = [];
        foreach ($urls as $url) {
            if (!$url instanceof RelayUrl) {
                throw new InvalidArgumentException(sprintf('RelaySet accepts only RelayUrl elements, got %s', get_debug_type($url)));
            }
            $byUrl[(string) $url] ??= $url;
        }
        $this->byUrl = $byUrl;
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function fromStrings(iterable $values): self
    {
        $urls = [];
        foreach ($values as $value) {
            $url = is_string($value) ? RelayUrl::tryFromString($value) : null;
            if (null !== $url) {
                $urls[] = $url;
            }
        }

        return new self($urls);
    }

    public function union(self ...$others): self
    {
        return new self(array_merge(array_values($this->byUrl), ...array_map(static fn (self $other): array => $other->toArray(), $others)));
    }

    public function without(self $blocked): self
    {
        return new self(array_values(array_diff_key($this->byUrl, $blocked->byUrl)));
    }

    public function take(int $count): self
    {
        if ($count < 0) {
            throw new InvalidArgumentException(sprintf('Cannot take a negative number of relays, got %d', $count));
        }

        return new self(array_slice(array_values($this->byUrl), 0, $count));
    }

    public function first(): ?RelayUrl
    {
        return $this->byUrl[array_key_first($this->byUrl) ?? ''] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->byUrl;
    }

    #[Override]
    public function count(): int
    {
        return count($this->byUrl);
    }

    /**
     * @return ArrayIterator<int, RelayUrl>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(array_values($this->byUrl));
    }

    /**
     * @return list<RelayUrl>
     */
    public function toArray(): array
    {
        return array_values($this->byUrl);
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return array_map(strval(...), array_keys($this->byUrl));
    }
}
