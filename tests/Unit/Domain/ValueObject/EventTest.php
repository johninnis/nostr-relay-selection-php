<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
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
}
