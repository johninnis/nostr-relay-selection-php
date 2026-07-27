<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorReadRouteContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\AuthorReadRoute;

final class AuthorReadRouter
{
    /**
     * @return list<AuthorReadRoute>
     */
    public static function route(AuthorReadRouteContext $context): array
    {
        $uniqueAuthors = self::deduplicate($context->getAuthorPubkeys());
        $cap = $context->getMaxAuthorsPerFilter();
        $target = $context->getRedundancy();
        $blocked = $context->getBlockedRelays();

        $relayToAuthors = self::filterBlocked(
            self::buildRelayToAuthorsMap($uniqueAuthors, $context),
            $blocked,
        );
        $authorsWithRelays = self::collectAuthorsWithRelays($relayToAuthors);
        $maxCoverByAuthor = self::buildMaxCoverByAuthor($relayToAuthors);

        $picks = self::greedySetCover($relayToAuthors, $maxCoverByAuthor, $target);
        $routes = self::picksToRoutes($picks, $cap);

        $authorsWithoutRelays = self::filterAuthorsWithoutRelays($uniqueAuthors, $authorsWithRelays);
        if ([] !== $authorsWithoutRelays) {
            $fallback = RelaySetBuilder::subtract($context->getFallbackRelays(), $blocked);
            $routes[] = new AuthorReadRoute($fallback, self::chunkPubkeys($authorsWithoutRelays, $cap));
        }

        return $routes;
    }

    /**
     * @param array<string, array{relay: RelayUrl, authors: array<string, PublicKey>}> $relayToAuthors
     * @param list<RelayUrl>                                                           $blocked
     *
     * @return array<string, array{relay: RelayUrl, authors: array<string, PublicKey>}>
     */
    private static function filterBlocked(array $relayToAuthors, array $blocked): array
    {
        if ([] === $blocked) {
            return $relayToAuthors;
        }
        $blockedKeys = [];
        foreach ($blocked as $url) {
            $blockedKeys[(string) $url] = true;
        }
        foreach (array_keys($relayToAuthors) as $key) {
            if (isset($blockedKeys[$key])) {
                unset($relayToAuthors[$key]);
            }
        }

        return $relayToAuthors;
    }

    /**
     * @param list<PublicKey> $authors
     *
     * @return list<PublicKey>
     */
    private static function deduplicate(array $authors): array
    {
        $seen = [];
        $unique = [];
        foreach ($authors as $author) {
            $hex = $author->toHex();
            if (isset($seen[$hex])) {
                continue;
            }
            $seen[$hex] = true;
            $unique[] = $author;
        }

        return $unique;
    }

    /**
     * @param list<PublicKey> $authors
     *
     * @return array<string, array{relay: RelayUrl, authors: array<string, PublicKey>}>
     */
    private static function buildRelayToAuthorsMap(array $authors, AuthorReadRouteContext $context): array
    {
        $map = [];
        foreach ($authors as $author) {
            $list = EventSelector::newestByPubkeyAndKind(
                $context->getRelayListEvents(),
                $author,
                EventKind::RelayList->value,
            );
            if (null === $list) {
                continue;
            }
            $outbox = RelayListExtractor::outbox($list->getTags());
            if ([] === $outbox) {
                continue;
            }
            foreach ($outbox as $relay) {
                $key = (string) $relay;
                if (!isset($map[$key])) {
                    $map[$key] = ['relay' => $relay, 'authors' => []];
                }
                $map[$key]['authors'][$author->toHex()] = $author;
            }
        }

        return $map;
    }

    /**
     * @param array<string, array{relay: RelayUrl, authors: array<string, PublicKey>}> $relayToAuthors
     *
     * @return array<string, true>
     */
    private static function collectAuthorsWithRelays(array $relayToAuthors): array
    {
        $set = [];
        foreach ($relayToAuthors as $entry) {
            foreach ($entry['authors'] as $hex => $_pubkey) {
                $set[$hex] = true;
            }
        }

        return $set;
    }

    /**
     * @param array<string, array{relay: RelayUrl, authors: array<string, PublicKey>}> $relayToAuthors
     *
     * @return array<string, int>
     */
    private static function buildMaxCoverByAuthor(array $relayToAuthors): array
    {
        $counts = [];
        foreach ($relayToAuthors as $entry) {
            foreach ($entry['authors'] as $hex => $_pubkey) {
                $counts[$hex] = ($counts[$hex] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param array<string, array{relay: RelayUrl, authors: array<string, PublicKey>}> $relayToAuthors
     * @param array<string, int>                                                       $maxCoverByAuthor
     *
     * @return list<array{relay: RelayUrl, authors: list<PublicKey>}>
     */
    private static function greedySetCover(array $relayToAuthors, array $maxCoverByAuthor, ?int $target): array
    {
        $coverByAuthor = [];
        $remaining = $relayToAuthors;
        $picks = [];

        while ([] !== $remaining) {
            $bestKey = null;
            $bestRelay = null;
            $bestAuthors = [];
            foreach ($remaining as $key => $entry) {
                $needed = [];
                foreach ($entry['authors'] as $hex => $pubkey) {
                    $maxCover = $maxCoverByAuthor[$hex] ?? 0;
                    $authorTarget = null === $target ? $maxCover : min($target, $maxCover);
                    if (($coverByAuthor[$hex] ?? 0) < $authorTarget) {
                        $needed[] = $pubkey;
                    }
                }
                if (count($needed) > count($bestAuthors)) {
                    $bestKey = $key;
                    $bestRelay = $entry['relay'];
                    $bestAuthors = $needed;
                }
            }
            if (null === $bestKey || null === $bestRelay || [] === $bestAuthors) {
                break;
            }
            $picks[] = ['relay' => $bestRelay, 'authors' => $bestAuthors];
            unset($remaining[$bestKey]);
            foreach ($bestAuthors as $pubkey) {
                $hex = $pubkey->toHex();
                $coverByAuthor[$hex] = ($coverByAuthor[$hex] ?? 0) + 1;
            }
        }

        return $picks;
    }

    /**
     * @param list<array{relay: RelayUrl, authors: list<PublicKey>}> $picks
     *
     * @return list<AuthorReadRoute>
     */
    private static function picksToRoutes(array $picks, int $cap): array
    {
        $routes = [];
        foreach ($picks as $pick) {
            $routes[] = new AuthorReadRoute([$pick['relay']], self::chunkPubkeys($pick['authors'], $cap));
        }

        return $routes;
    }

    /**
     * @param list<PublicKey>     $authors
     * @param array<string, true> $authorsWithRelays
     *
     * @return list<PublicKey>
     */
    private static function filterAuthorsWithoutRelays(array $authors, array $authorsWithRelays): array
    {
        $result = [];
        foreach ($authors as $author) {
            if (!isset($authorsWithRelays[$author->toHex()])) {
                $result[] = $author;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, PublicKey> $items
     *
     * @return list<list<PublicKey>>
     */
    private static function chunkPubkeys(array $items, int $size): array
    {
        if ($size <= 0 || count($items) <= $size) {
            return [array_values($items)];
        }

        return array_chunk($items, $size);
    }
}
