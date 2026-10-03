<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0003

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol;

use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class Filter
{
    /** @var ?list<int> */
    private ?array $kinds;

    /**
     * @param ?array<array-key, mixed> $kinds
     */
    public function __construct(
        ?array $kinds = null,
        private ?PublicKeyCollection $pTags = null,
        private ?string $search = null,
    ) {
        $this->kinds = null === $kinds ? null : array_values(array_map(
            static fn (mixed $kind): int => is_int($kind)
                ? $kind
                : throw new InvalidArgumentException(sprintf('Filter kinds must be integers, got %s', get_debug_type($kind))),
            $kinds,
        ));
    }

    /**
     * @return ?list<int>
     */
    public function getKinds(): ?array
    {
        return $this->kinds;
    }

    public function getPTags(): ?PublicKeyCollection
    {
        return $this->pTags;
    }

    public function hasSearch(): bool
    {
        return null !== $this->search && '' !== $this->search;
    }

    public static function tryFromRaw(mixed $raw): ?self
    {
        if (!is_array($raw) || ([] !== $raw && array_is_list($raw))) {
            return null;
        }
        $kinds = $raw['kinds'] ?? null;
        $pTags = array_key_exists('#p', $raw) ? self::tryPubkeysFrom($raw['#p']) : null;
        $search = $raw['search'] ?? null;
        $validKinds = !array_key_exists('kinds', $raw) || (is_array($kinds) && array_is_list($kinds) && array_all($kinds, static fn (mixed $kind): bool => is_int($kind)));
        $validPTags = !array_key_exists('#p', $raw) || null !== $pTags;
        $validSearch = !array_key_exists('search', $raw) || is_string($search);
        if (!$validKinds || !$validPTags || !$validSearch) {
            return null;
        }

        return new self(is_array($kinds) ? $kinds : null, $pTags, is_string($search) ? $search : null);
    }

    private static function tryPubkeysFrom(mixed $raw): ?PublicKeyCollection
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return null;
        }
        $pubkeys = array_map(static fn (mixed $hex): ?PublicKey => is_string($hex) ? PublicKey::tryFromHex($hex) : null, $raw);

        return in_array(null, $pubkeys, true) ? null : new PublicKeyCollection($pubkeys);
    }
}
