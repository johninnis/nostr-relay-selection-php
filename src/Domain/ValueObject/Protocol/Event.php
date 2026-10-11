<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0003

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol;

use Innis\Nostr\RelaySelection\Domain\Collection\TagCollection;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class Event
{
    private const int MAX_KIND = 65535;

    // Deliberate: the five fields are the protocol record's own shape, not a hidden responsibility — see ADR-0004
    public function __construct(
        private EventId $id,
        private int $kind,
        private PublicKey $pubkey,
        private int $createdAt,
        private TagCollection $tags,
    ) {
        if (!self::isKind($kind)) {
            throw new InvalidArgumentException(sprintf('An event kind must be between 0 and %d, got %d', self::MAX_KIND, $kind));
        }
        if (!self::isCreatedAt($createdAt)) {
            throw new InvalidArgumentException(sprintf('An event created_at cannot be negative, got %d', $createdAt));
        }
    }

    public function getId(): EventId
    {
        return $this->id;
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getTags(): TagCollection
    {
        return $this->tags;
    }

    public static function tryFromRaw(mixed $raw): ?self
    {
        if (!is_array($raw) || !is_string($raw['id'] ?? null) || !is_int($raw['kind'] ?? null) || !is_string($raw['pubkey'] ?? null)
            || !is_int($raw['created_at'] ?? null) || !is_array($raw['tags'] ?? null) || !array_is_list($raw['tags'])) {
            return null;
        }
        $id = EventId::tryFromHex($raw['id']);
        $pubkey = PublicKey::tryFromHex($raw['pubkey']);
        $tags = array_map(Tag::tryFromRaw(...), $raw['tags']);
        if (null === $id || null === $pubkey || !self::isKind($raw['kind']) || !self::isCreatedAt($raw['created_at']) || in_array(null, $tags, true)) {
            return null;
        }

        return new self($id, $raw['kind'], $pubkey, $raw['created_at'], new TagCollection($tags));
    }

    private static function isKind(int $kind): bool
    {
        return $kind >= 0 && $kind <= self::MAX_KIND;
    }

    private static function isCreatedAt(int $createdAt): bool
    {
        return $createdAt >= 0;
    }
}
