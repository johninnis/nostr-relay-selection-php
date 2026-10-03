<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ZeroDependencyFenceTest extends TestCase
{
    public function testComposerRequiresNothingButPhp(): void
    {
        $composer = json_decode(
            file_get_contents(__DIR__.'/../../composer.json') ?: throw new RuntimeException('Missing composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(['php'], array_keys(is_array($composer) && is_array($composer['require'] ?? null) ? $composer['require'] : []));
    }
}
