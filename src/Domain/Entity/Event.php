<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0002

namespace Innis\Nostr\RelaySelection\Domain\Entity;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;
use InvalidArgumentException;

final readonly class Event
{
    /** @var list<Tag> */
    private array $tags;

    // Deliberate: the four fields are the protocol record's own shape, not a hidden responsibility — see ADR-0001
    /**
     * @param array<array-key, mixed> $tags
     */
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

    /**
     * @return list<Tag>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public static function tryFromRaw(mixed $raw): ?self
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
        $pubkey = PublicKey::tryFromHex($raw['pubkey']);
        if (null === $pubkey) {
            return null;
        }
        $tags = [];
        foreach ($raw['tags'] as $rawTag) {
            $tag = Tag::tryFromRaw($rawTag);
            if (null === $tag) {
                return null;
            }
            $tags[] = $tag;
        }

        return new self($raw['kind'], $pubkey, $raw['created_at'], $tags);
    }
}
