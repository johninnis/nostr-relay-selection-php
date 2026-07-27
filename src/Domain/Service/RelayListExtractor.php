<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;

final class RelayListExtractor
{
    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<RelayUrl>
     */
    public static function inbox(array $tags): array
    {
        return self::fromRTags($tags, ['read', 'both']);
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<RelayUrl>
     */
    public static function outbox(array $tags): array
    {
        return self::fromRTags($tags, ['write', 'both']);
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<RelayUrl>
     */
    public static function dm(array $tags): array
    {
        return self::fromRelayTags($tags);
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<RelayUrl>
     */
    public static function blocked(array $tags): array
    {
        return self::fromRelayTags($tags);
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<RelayUrl>
     */
    public static function search(array $tags): array
    {
        return self::fromRelayTags($tags);
    }

    /**
     * @param array<array-key, mixed> $tags
     * @param list<string>            $acceptedMarkers
     *
     * @return list<RelayUrl>
     */
    private static function fromRTags(array $tags, array $acceptedMarkers): array
    {
        $result = [];
        foreach ($tags as $tag) {
            if (!$tag instanceof Tag || 'r' !== $tag->getValue(0)) {
                continue;
            }
            $rawUrl = $tag->getValue(1);
            if (null === $rawUrl) {
                continue;
            }
            $marker = $tag->getValue(2) ?? 'both';
            if (!in_array($marker, $acceptedMarkers, true)) {
                continue;
            }
            $url = RelayUrl::tryFromString($rawUrl);
            if (null !== $url) {
                $result[] = $url;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<RelayUrl>
     */
    private static function fromRelayTags(array $tags): array
    {
        $result = [];
        foreach ($tags as $tag) {
            if (!$tag instanceof Tag || 'relay' !== $tag->getValue(0)) {
                continue;
            }
            $rawUrl = $tag->getValue(1);
            if (null === $rawUrl) {
                continue;
            }
            $url = RelayUrl::tryFromString($rawUrl);
            if (null !== $url) {
                $result[] = $url;
            }
        }

        return $result;
    }
}
