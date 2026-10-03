<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\AuthorReadRouteCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\AuthorReadPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PositiveCount;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\Redundancy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\AuthorReadRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;

final class AuthorReadRouter
{
    public static function route(
        PublicKeyCollection $authors,
        RelayDirectory $directory,
        AuthorReadPolicy $policy = new AuthorReadPolicy(),
    ): AuthorReadRouteCollection {
        $outboxes = [];
        foreach ($authors as $author) {
            $outboxes[$author->toHex()] ??= ['author' => $author, 'outbox' => $directory->relaysOf($author, RelayRole::Outbox)];
        }
        $chunkSize = $policy->getMaxAuthorsPerFilter();
        $routes = array_map(
            static fn (array $pick): AuthorReadRoute => new AuthorReadRoute(new RelaySet([$pick['relay']]), self::chunk($pick['authors'], $chunkSize)),
            self::greedyCover(self::candidatesOf($outboxes), $policy->getRedundancy()),
        );
        $uncovered = array_values(array_map(
            static fn (array $entry): PublicKey => $entry['author'],
            array_filter($outboxes, static fn (array $entry): bool => $entry['outbox']->isEmpty()),
        ));
        $fallback = $directory->permitted($policy->getFallbackRelays());
        if ([] !== $uncovered && !$fallback->isEmpty()) {
            $routes[] = new AuthorReadRoute($fallback, self::chunk($uncovered, $chunkSize));
        }

        return new AuthorReadRouteCollection($routes);
    }

    /**
     * @param array<string, array{author: PublicKey, outbox: RelaySet}> $outboxes
     *
     * @return list<array{relay: RelayUrl, authors: list<PublicKey>}>
     */
    private static function candidatesOf(array $outboxes): array
    {
        $candidates = [];
        foreach ($outboxes as $entry) {
            foreach ($entry['outbox'] as $relay) {
                $candidates[(string) $relay] ??= ['relay' => $relay, 'authors' => []];
                $candidates[(string) $relay]['authors'][] = $entry['author'];
            }
        }

        return array_values($candidates);
    }

    /*
     * Greedy set cover: repeatedly pick the relay that reaches the most authors who
     * are still below the redundancy target, until no relay reaches such an author.
     * Ties go to the relay seen first, so the plan is deterministic.
     */
    /**
     * @param list<array{relay: RelayUrl, authors: list<PublicKey>}> $candidates
     *
     * @return list<array{relay: RelayUrl, authors: list<PublicKey>}>
     */
    private static function greedyCover(array $candidates, Redundancy $redundancy): array
    {
        $coverage = [];
        $picks = [];
        $remaining = $candidates;
        while (null !== ($best = self::bestOf(self::stillNeeding($remaining, $coverage, $redundancy)))) {
            $picks[] = $best;
            $picked = (string) $best['relay'];
            $remaining = array_values(array_filter($remaining, static fn (array $candidate): bool => (string) $candidate['relay'] !== $picked));
            foreach ($best['authors'] as $author) {
                $coverage[$author->toHex()] = ($coverage[$author->toHex()] ?? 0) + 1;
            }
        }

        return $picks;
    }

    /**
     * @param list<array{relay: RelayUrl, authors: list<PublicKey>}> $candidates
     * @param array<string, int>                                     $coverage
     *
     * @return list<array{relay: RelayUrl, authors: list<PublicKey>}>
     */
    private static function stillNeeding(array $candidates, array $coverage, Redundancy $redundancy): array
    {
        return array_map(static fn (array $candidate): array => [
            'relay' => $candidate['relay'],
            'authors' => array_values(array_filter(
                $candidate['authors'],
                static fn (PublicKey $author): bool => !$redundancy->isReachedAt($coverage[$author->toHex()] ?? 0),
            )),
        ], $candidates);
    }

    /**
     * @param list<array{relay: RelayUrl, authors: list<PublicKey>}> $candidates
     *
     * @return ?array{relay: RelayUrl, authors: list<PublicKey>}
     */
    private static function bestOf(array $candidates): ?array
    {
        $best = null;
        foreach ($candidates as $candidate) {
            if (count($candidate['authors']) > count($best['authors'] ?? [])) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param list<PublicKey> $authors
     *
     * @return list<PublicKeyCollection>
     */
    private static function chunk(array $authors, PositiveCount $size): array
    {
        return array_map(
            static fn (array $chunk): PublicKeyCollection => new PublicKeyCollection($chunk),
            array_chunk($authors, $size->getValue()),
        );
    }
}
