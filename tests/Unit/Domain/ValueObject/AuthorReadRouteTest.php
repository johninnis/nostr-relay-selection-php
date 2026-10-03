<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\AuthorReadRoute;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthorReadRouteTest extends TestCase
{
    public function testRejectsARouteWithNoRelays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AuthorReadRoute(new RelaySet(), []);
    }
}
