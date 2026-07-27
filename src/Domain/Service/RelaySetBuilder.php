<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final class RelaySetBuilder
{
    /**
     * @param array<array-key, mixed> ...$sources
     *
     * @return list<RelayUrl>
     */
    public static function build(array ...$sources): array
    {
        $seen = [];
        $result = [];
        foreach ($sources as $source) {
            foreach ($source as $url) {
                if (!$url instanceof RelayUrl) {
                    continue;
                }
                $key = (string) $url;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = $url;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $relays
     * @param array<array-key, mixed> $blocked
     *
     * @return list<RelayUrl>
     */
    public static function subtract(array $relays, array $blocked): array
    {
        $blockedKeys = [];
        foreach ($blocked as $url) {
            if ($url instanceof RelayUrl) {
                $blockedKeys[(string) $url] = true;
            }
        }

        $result = [];
        foreach ($relays as $url) {
            if (!$url instanceof RelayUrl) {
                continue;
            }
            if (isset($blockedKeys[(string) $url])) {
                continue;
            }
            $result[] = $url;
        }

        return $result;
    }
}
