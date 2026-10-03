<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Routing;

use Innis\Nostr\RelaySelection\Domain\Collection\EventCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;

final readonly class RelayDirectory
{
    /**
     * @param array<string, Event> $newest
     */
    private function __construct(
        private array $newest,
        private RelaySet $blocked,
    ) {
    }

    public static function fromEvents(EventCollection $relayListEvents, RelaySet $blocked = new RelaySet()): self
    {
        $newest = [];
        foreach ($relayListEvents as $event) {
            $key = self::keyOf($event->getPubkey(), $event->getKind());
            if (self::supersedes($event, $newest[$key] ?? null)) {
                $newest[$key] = $event;
            }
        }

        return new self($newest, $blocked);
    }

    public function getBlocked(): RelaySet
    {
        return $this->blocked;
    }

    public function relaysOf(PublicKey $pubkey, RelayRole $role): RelaySet
    {
        $list = $this->newest[self::keyOf($pubkey, $role->kind()->value)] ?? null;

        return null === $list ? new RelaySet() : $this->permitted($role->extract($list->getTags()));
    }

    public function permitted(RelaySet ...$sources): RelaySet
    {
        return new RelaySet()->union(...$sources)->without($this->blocked);
    }

    private static function keyOf(PublicKey $pubkey, int $kind): string
    {
        return $pubkey->toHex().':'.$kind;
    }

    private static function supersedes(Event $candidate, ?Event $current): bool
    {
        return null === $current
            || $candidate->getCreatedAt() > $current->getCreatedAt()
            || ($candidate->getCreatedAt() === $current->getCreatedAt() && $candidate->getId()->isLowerThan($current->getId()));
    }
}
