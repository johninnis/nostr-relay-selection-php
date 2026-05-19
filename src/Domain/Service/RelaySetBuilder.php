<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final class RelaySetBuilder
{
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

    public static function subtract(array $relays, array $blocked): array
    {
        if ([] === $blocked) {
            return $relays;
        }

        $blockedKeys = [];
        foreach ($blocked as $url) {
            if ($url instanceof RelayUrl) {
                $blockedKeys[(string) $url] = true;
            }
        }

        if ([] === $blockedKeys) {
            return $relays;
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
