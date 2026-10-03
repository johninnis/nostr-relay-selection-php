<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Acceptance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExampleScriptsTest extends TestCase
{
    #[DataProvider('exampleScripts')]
    public function testExampleRunsToASuccessfulExit(string $script): void
    {
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function exampleScripts(): iterable
    {
        foreach (glob(__DIR__.'/../../examples/*.php') ?: throw new RuntimeException('No example scripts found') as $script) {
            yield basename($script) => [$script];
        }
    }
}
