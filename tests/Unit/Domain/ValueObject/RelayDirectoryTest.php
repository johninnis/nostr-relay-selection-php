<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\Collection\EventCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Collection\TagCollection;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RelayDirectoryTest extends TestCase
{
    private static function alice(): PublicKey
    {
        return PublicKey::tryFromHex(str_repeat('a', 64)) ?? throw new InvalidArgumentException('Invalid test pubkey');
    }

    private static function relayList(string $idSuffix, int $createdAt, string $url): Event
    {
        $id = EventId::tryFromHex(str_repeat('0', 63).$idSuffix) ?? throw new InvalidArgumentException('Invalid test id');

        return new Event($id, 10002, self::alice(), $createdAt, new TagCollection([new Tag(['r', $url])]));
    }

    public function testTheLowestIdWinsATieWhicheverComesFirst(): void
    {
        $low = self::relayList('1', 100, 'wss://low.example.com');
        $high = self::relayList('2', 100, 'wss://high.example.com');
        $this->assertSame(
            [['wss://low.example.com'], ['wss://low.example.com']],
            [
                RelayDirectory::fromEvents(new EventCollection([$low, $high]))->relaysOf(self::alice(), RelayRole::Inbox)->toStrings(),
                RelayDirectory::fromEvents(new EventCollection([$high, $low]))->relaysOf(self::alice(), RelayRole::Inbox)->toStrings(),
            ],
        );
    }

    public function testPermittedUnionsSourcesAndDropsBlockedRelays(): void
    {
        $directory = RelayDirectory::fromEvents(new EventCollection(), RelaySet::fromStrings(['wss://blocked.example.com']));
        $permitted = $directory->permitted(
            RelaySet::fromStrings(['wss://a.example.com', 'wss://blocked.example.com']),
            RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com']),
        );
        $this->assertSame(['wss://a.example.com', 'wss://b.example.com'], $permitted->toStrings());
    }

    public function testExposesItsBlocklist(): void
    {
        $blocked = RelaySet::fromStrings(['wss://blocked.example.com']);
        $this->assertSame($blocked, RelayDirectory::fromEvents(new EventCollection(), $blocked)->getBlocked());
    }

    public function testAnUnknownPubkeyHasNoRelays(): void
    {
        $this->assertTrue(RelayDirectory::fromEvents(new EventCollection())->relaysOf(self::alice(), RelayRole::Outbox)->isEmpty());
    }
}
