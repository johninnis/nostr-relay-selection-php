<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;

final class RelayUrlTest extends TestCase
{
    public function testFromStringReturnsNullForNullInput(): void
    {
        $this->assertNull(RelayUrl::tryFromString(null));
    }

    public function testFromStringReturnsNullForEmptyString(): void
    {
        $this->assertNull(RelayUrl::tryFromString(''));
    }

    public function testFromStringReturnsNullForWhitespaceOnly(): void
    {
        $this->assertNull(RelayUrl::tryFromString('   '));
    }

    public function testFromStringRejectsHttpScheme(): void
    {
        $this->assertNull(RelayUrl::tryFromString('http://relay.example.com'));
    }

    public function testFromStringRejectsHttpsScheme(): void
    {
        $this->assertNull(RelayUrl::tryFromString('https://relay.example.com'));
    }

    public function testFromStringAcceptsWssScheme(): void
    {
        $url = RelayUrl::tryFromString('wss://relay.example.com');
        $this->assertNotNull($url);
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringAcceptsWsScheme(): void
    {
        $url = RelayUrl::tryFromString('ws://relay.example.com');
        $this->assertNotNull($url);
        $this->assertSame('ws://relay.example.com', (string) $url);
    }

    public function testFromStringLowercasesScheme(): void
    {
        $url = RelayUrl::tryFromString('WSS://relay.example.com');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringLowercasesHost(): void
    {
        $url = RelayUrl::tryFromString('wss://Relay.Example.COM');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringStripsDefaultWssPort(): void
    {
        $url = RelayUrl::tryFromString('wss://relay.example.com:443');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringStripsDefaultWsPort(): void
    {
        $url = RelayUrl::tryFromString('ws://relay.example.com:80');
        $this->assertSame('ws://relay.example.com', (string) $url);
    }

    public function testFromStringPreservesNonDefaultPort(): void
    {
        $url = RelayUrl::tryFromString('wss://relay.example.com:444');
        $this->assertSame('wss://relay.example.com:444', (string) $url);
    }

    public function testFromStringStripsTrailingSlash(): void
    {
        $url = RelayUrl::tryFromString('wss://relay.example.com/');
        $this->assertSame('wss://relay.example.com', (string) $url);
    }

    public function testFromStringStripsTrailingComma(): void
    {
        $url = RelayUrl::tryFromString('wss://relay.snort.social/,');
        $this->assertSame('wss://relay.snort.social', (string) $url);
    }

    public function testFromStringStripsTrailingPeriod(): void
    {
        $url = RelayUrl::tryFromString('wss://relay.damus.io/.');
        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testFromStringRejectsFragment(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com/#fragment'));
    }

    public function testFromStringRejectsPercentEncodedSpaceInPath(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com/path%20with%20spaces'));
    }

    public function testFromStringRejectsSpaceInHost(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay example.com'));
    }

    public function testFromStringRejectsHostStartingWithDot(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://.relay.example.com'));
    }

    public function testFromStringRejectsHostEndingWithDot(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com.'));
    }

    public function testFromStringRejectsPortZero(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com:0'));
    }

    public function testFromStringRejectsPortAboveMax(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com:70000'));
    }

    public function testFromStringRejectsDoubleSlashesInPath(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com//bad'));
    }

    public function testFromStringRejectsHostnameInPath(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.snort.social/relay.snort.social'));
    }

    public function testFromStringRejectsConcatenatedUrls(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com/wss://other.relay.com'));
    }

    public function testFromStringRejectsUrlExceeding200Characters(): void
    {
        $longPath = str_repeat('a', 180);
        $this->assertNull(RelayUrl::tryFromString('wss://relay.example.com/'.$longPath));
    }

    public function testFromStringRejectsUnicodeInHost(): void
    {
        $this->assertNull(RelayUrl::tryFromString('wss://⬤ wss//nostr-pub.wellorder.net'));
    }

    public function testEqualsReturnsTrueForIdenticalUrls(): void
    {
        $a = RelayUrl::tryFromString('wss://relay.example.com');
        $b = RelayUrl::tryFromString('wss://relay.example.com');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentUrls(): void
    {
        $a = RelayUrl::tryFromString('wss://a.example.com');
        $b = RelayUrl::tryFromString('wss://b.example.com');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertFalse($a->equals($b));
    }

    public function testEqualsIsTrueAcrossExplicitAndImplicitDefaultPort(): void
    {
        $bare = RelayUrl::tryFromString('wss://relay.example.com');
        $explicit = RelayUrl::tryFromString('wss://relay.example.com:443');
        $this->assertNotNull($bare);
        $this->assertNotNull($explicit);
        $this->assertTrue($bare->equals($explicit));
    }
}
