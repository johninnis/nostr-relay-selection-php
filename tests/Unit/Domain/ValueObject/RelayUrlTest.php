<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;

final class RelayUrlTest extends TestCase
{
    public function testFromStringReturnsNullForNullInput(): void
    {
        $this->assertNull(RelayUrl::fromString(null));
    }

    public function testFromStringReturnsNullForEmptyString(): void
    {
        $this->assertNull(RelayUrl::fromString(''));
    }

    public function testFromStringReturnsNullForWhitespaceOnly(): void
    {
        $this->assertNull(RelayUrl::fromString('   '));
    }

    public function testFromStringRejectsHttpScheme(): void
    {
        $this->assertNull(RelayUrl::fromString('http://relay.example.com'));
    }

    public function testFromStringRejectsHttpsScheme(): void
    {
        $this->assertNull(RelayUrl::fromString('https://relay.example.com'));
    }

    public function testFromStringAcceptsWssScheme(): void
    {
        $url = RelayUrl::fromString('wss://relay.example.com');
        $this->assertNotNull($url);
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringAcceptsWsScheme(): void
    {
        $url = RelayUrl::fromString('ws://relay.example.com');
        $this->assertNotNull($url);
        $this->assertSame('ws://relay.example.com', (string) $url);
    }

    public function testFromStringLowercasesScheme(): void
    {
        $url = RelayUrl::fromString('WSS://relay.example.com');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringLowercasesHost(): void
    {
        $url = RelayUrl::fromString('wss://Relay.Example.COM');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringStripsDefaultWssPort(): void
    {
        $url = RelayUrl::fromString('wss://relay.example.com:443');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringStripsDefaultWsPort(): void
    {
        $url = RelayUrl::fromString('ws://relay.example.com:80');
        $this->assertSame('ws://relay.example.com', (string) $url);
    }

    public function testFromStringPreservesNonDefaultPort(): void
    {
        $url = RelayUrl::fromString('wss://relay.example.com:444');
        $this->assertSame('wss://relay.example.com:444', (string) $url);
    }

    public function testFromStringStripsTrailingSlash(): void
    {
        $url = RelayUrl::fromString('wss://relay.example.com/');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringStripsTrailingComma(): void
    {
        $url = RelayUrl::fromString('wss://relay.snort.social/,');
        $this->assertSame('wss://relay.snort.social', (string) $url);
    }

    public function testFromStringStripsTrailingPeriod(): void
    {
        $url = RelayUrl::fromString('wss://relay.damus.io/.');
        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testFromStringRejectsFragment(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com/#fragment'));
    }

    public function testFromStringRejectsPercentEncodedSpaceInPath(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com/path%20with%20spaces'));
    }

    public function testFromStringRejectsSpaceInHost(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay example.com'));
    }

    public function testFromStringRejectsHostStartingWithDot(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://.relay.example.com'));
    }

    public function testFromStringRejectsHostEndingWithDot(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com.'));
    }

    public function testFromStringRejectsPortZero(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com:0'));
    }

    public function testFromStringRejectsPortAboveMax(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com:70000'));
    }

    public function testFromStringRejectsDoubleSlashesInPath(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com//bad'));
    }

    public function testFromStringRejectsHostnameInPath(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.snort.social/relay.snort.social'));
    }

    public function testFromStringRejectsConcatenatedUrls(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com/wss://other.relay.com'));
    }

    public function testFromStringRejectsUrlExceeding200Characters(): void
    {
        $longPath = str_repeat('a', 180);
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com/'.$longPath));
    }

    public function testFromStringRejectsUnicodeInHost(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://⬤ wss//nostr-pub.wellorder.net'));
    }

    public function testEqualsReturnsTrueForIdenticalUrls(): void
    {
        $a = RelayUrl::fromString('wss://relay.example.com');
        $b = RelayUrl::fromString('wss://relay.example.com');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentUrls(): void
    {
        $a = RelayUrl::fromString('wss://a.example.com');
        $b = RelayUrl::fromString('wss://b.example.com');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertFalse($a->equals($b));
    }

    public function testEqualsIsTrueAcrossExplicitAndImplicitDefaultPort(): void
    {
        $bare = RelayUrl::fromString('wss://relay.example.com');
        $explicit = RelayUrl::fromString('wss://relay.example.com:443');
        $this->assertNotNull($bare);
        $this->assertNotNull($explicit);
        $this->assertTrue($bare->equals($explicit));
    }
}
