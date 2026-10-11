<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\Collection\TagCollection;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function raw(): array
    {
        return ['id' => str_repeat('e', 64), 'kind' => 1, 'pubkey' => str_repeat('a', 64), 'created_at' => 100, 'tags' => [['p', str_repeat('b', 64)]]];
    }

    private static function construct(int $kind, int $createdAt): Event
    {
        $id = EventId::tryFromHex(str_repeat('e', 64)) ?? throw new InvalidArgumentException('Invalid test id');
        $pubkey = PublicKey::tryFromHex(str_repeat('a', 64)) ?? throw new InvalidArgumentException('Invalid test pubkey');

        return new Event($id, $kind, $pubkey, $createdAt, new TagCollection());
    }

    public function testTryFromRawKeepsEveryField(): void
    {
        $event = Event::tryFromRaw(self::raw());
        $this->assertNotNull($event);
        $this->assertSame(
            [str_repeat('e', 64), 1, str_repeat('a', 64), 100, 1],
            [$event->getId()->toHex(), $event->getKind(), $event->getPubkey()->toHex(), $event->getCreatedAt(), count($event->getTags())],
        );
    }

    public function testTryFromRawRequiresAnId(): void
    {
        $raw = self::raw();
        unset($raw['id']);
        $this->assertNull(Event::tryFromRaw($raw));
    }

    public function testTryFromRawRejectsAMalformedTag(): void
    {
        $this->assertNull(Event::tryFromRaw([...self::raw(), 'tags' => [['p', 1]]]));
    }

    public function testTryFromRawKeepsACreatedAtOfPhpIntMax(): void
    {
        $this->assertSame(PHP_INT_MAX, Event::tryFromRaw([...self::raw(), 'created_at' => PHP_INT_MAX])?->getCreatedAt());
    }

    public function testTryFromRawRejectsACreatedAtBeyondTheIntegerRange(): void
    {
        $this->assertNull(Event::tryFromRaw([...self::raw(), 'created_at' => (float) PHP_INT_MAX * 2]));
    }

    public function testConstructorRejectsAKindAbove65535(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::construct(65536, 100);
    }

    public function testConstructorRejectsANegativeKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::construct(-1, 100);
    }

    public function testConstructorRejectsANegativeCreatedAt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::construct(1, -1);
    }

    public function testConstructorAcceptsTheLargestKind(): void
    {
        $this->assertSame(65535, self::construct(65535, 100)->getKind());
    }

    public function testConstructorAcceptsTheEarliestTime(): void
    {
        $this->assertSame(0, self::construct(1, 0)->getCreatedAt());
    }
}
