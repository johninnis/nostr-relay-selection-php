<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0003

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol;

use Innis\Nostr\RelaySelection\Domain\Collection\TagCollection;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class Event
{
    // Deliberate: the five fields are the protocol record's own shape, not a hidden responsibility — see ADR-0004
    public function __construct(
        private EventId $id,
        private int $kind,
        private PublicKey $pubkey,
        private int $createdAt,
        private TagCollection $tags,
    ) {
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
        if (null === $id || null === $pubkey || in_array(null, $tags, true)) {
            return null;
        }

        return new self($id, $raw['kind'], $pubkey, $raw['created_at'], new TagCollection($tags));
    }
}
