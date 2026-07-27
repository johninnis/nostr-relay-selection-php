<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0002

// Minimal duplicate of innis/nostr-core's Filter entity, scoped to the fields
// relay selection consumes (kinds, #p tag values, search). innis/nostr-relay-selection
// must not depend on innis/nostr-core, so the type is re-declared here. Other
// filter fields exist in the wire protocol but are not consulted by routing
// decisions.

namespace Innis\Nostr\RelaySelection\Domain\Entity;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class Filter
{
    /** @var ?list<int> */
    private ?array $kinds;
    /** @var ?list<PublicKey> */
    private ?array $pTags;

    /**
     * @param ?array<array-key, mixed> $kinds
     * @param ?array<array-key, mixed> $pTags
     */
    public function __construct(
        ?array $kinds = null,
        ?array $pTags = null,
        private ?string $search = null,
    ) {
        if (null !== $kinds) {
            foreach ($kinds as $kind) {
                if (!is_int($kind)) {
                    throw new InvalidArgumentException('Filter kinds must be integers');
                }
            }
        }
        if (null !== $pTags) {
            foreach ($pTags as $pTag) {
                if (!$pTag instanceof PublicKey) {
                    throw new InvalidArgumentException('Filter pTags must be PublicKey instances');
                }
            }
        }
        $this->kinds = null !== $kinds ? array_values($kinds) : null;
        $this->pTags = null !== $pTags ? array_values($pTags) : null;
    }

    /**
     * @return ?list<int>
     */
    public function getKinds(): ?array
    {
        return $this->kinds;
    }

    /**
     * @return ?list<PublicKey>
     */
    public function getPTags(): ?array
    {
        return $this->pTags;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function hasSearch(): bool
    {
        return null !== $this->search && '' !== $this->search;
    }

    public static function tryFromRaw(mixed $raw): ?self
    {
        if (!is_array($raw)) {
            return null;
        }

        $kinds = null;
        if (isset($raw['kinds'])) {
            if (!is_array($raw['kinds'])) {
                return null;
            }
            $kinds = [];
            foreach ($raw['kinds'] as $kind) {
                if (!is_int($kind)) {
                    return null;
                }
                $kinds[] = $kind;
            }
        }

        $pTags = null;
        if (isset($raw['#p'])) {
            if (!is_array($raw['#p'])) {
                return null;
            }
            $pTags = [];
            foreach ($raw['#p'] as $rawPubkey) {
                if (!is_string($rawPubkey)) {
                    return null;
                }
                $pubkey = PublicKey::tryFromHex($rawPubkey);
                if (null === $pubkey) {
                    return null;
                }
                $pTags[] = $pubkey;
            }
        }

        $search = null;
        if (isset($raw['search'])) {
            if (!is_string($raw['search'])) {
                return null;
            }
            $search = $raw['search'];
        }

        return new self($kinds, $pTags, $search);
    }
}
