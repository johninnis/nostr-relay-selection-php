<?php

declare(strict_types=1);

// Minimal duplicate of innis/nostr-core's Event entity, scoped to the fields
// relay selection consumes (kind, pubkey, created_at, tags). innis/nostr-relay-selection
// must not depend on innis/nostr-core, so the type is re-declared here. There is
// no signature, no id, no content — relay routing reads only protocol-level
// fields.

namespace Innis\Nostr\RelaySelection\Domain\Entity;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;
use InvalidArgumentException;

final readonly class Event
{
    private array $tags;

    public function __construct(
        private int $kind,
        private PublicKey $pubkey,
        private int $createdAt,
        array $tags,
    ) {
        foreach ($tags as $tag) {
            if (!$tag instanceof Tag) {
                throw new InvalidArgumentException('Event tags must be Tag instances');
            }
        }
        $this->tags = array_values($tags);
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

    public function getTags(): array
    {
        return $this->tags;
    }

    public static function fromRaw(mixed $raw): ?self
    {
        if (!is_array($raw)) {
            return null;
        }
        if (!isset($raw['kind'], $raw['pubkey'], $raw['created_at'], $raw['tags'])) {
            return null;
        }
        if (!is_int($raw['kind']) || !is_int($raw['created_at']) || !is_string($raw['pubkey']) || !is_array($raw['tags'])) {
            return null;
        }
        $pubkey = PublicKey::fromHex($raw['pubkey']);
        if (null === $pubkey) {
            return null;
        }
        $tags = [];
        foreach ($raw['tags'] as $rawTag) {
            $tag = Tag::fromRaw($rawTag);
            if (null === $tag) {
                return null;
            }
            $tags[] = $tag;
        }

        return new self($raw['kind'], $pubkey, $raw['created_at'], $tags);
    }
}
