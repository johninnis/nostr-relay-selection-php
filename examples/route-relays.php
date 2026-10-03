<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Innis\Nostr\RelaySelection\Domain\Collection\EventCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\FilterCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Failure\NoDmRelaysFailure;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\PublishRouter;
use Innis\Nostr\RelaySelection\Domain\Service\ReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\RecipientsWithoutInboxFinder;
use Innis\Nostr\RelaySelection\Domain\Service\RelayHintSelector;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\AuthorReadPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PositiveCount;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\ReadPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\Redundancy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\PublishRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\ReadRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayHintTarget;

$alice = str_repeat('a', 64);
$bob = str_repeat('b', 64);
$carol = str_repeat('c', 64);
$names = [$alice => 'alice', $bob => 'bob', $carol => 'carol'];

function parseEvent(mixed $raw): Event
{
    return Event::tryFromRaw($raw) ?? throw new InvalidArgumentException('not a valid event: '.json_encode($raw));
}

function pubkey(string $hex): PublicKey
{
    return PublicKey::tryFromHex($hex) ?? throw new InvalidArgumentException('not a valid pubkey: '.$hex);
}

function describe(PublishRoute|ReadRoute|NoDmRelaysFailure $result): string
{
    if ($result instanceof NoDmRelaysFailure) {
        return 'refused ('.$result->value.')';
    }

    return $result->getBranch()->value.': '.(implode(', ', $result->getRelays()->toStrings()) ?: '(none)');
}

$wireRelayLists = [
    ['id' => str_repeat('1', 64), 'kind' => 10002, 'pubkey' => $alice, 'created_at' => 1700000000,
        'tags' => [['r', 'wss://alice-write.example.com', 'write'], ['r', 'wss://shared.example.com']]],
    ['id' => str_repeat('2', 64), 'kind' => 10002, 'pubkey' => $bob, 'created_at' => 1700000000,
        'tags' => [['r', 'WSS://Bob-Inbox.example.com/', 'read'], ['r', 'wss://shared.example.com']]],
    ['id' => str_repeat('3', 64), 'kind' => 10050, 'pubkey' => $bob, 'created_at' => 1700000000,
        'tags' => [['relay', 'wss://bob-dm.example.com']]],
];
$directory = RelayDirectory::fromEvents(
    new EventCollection(array_map(parseEvent(...), $wireRelayLists)),
    RelaySet::fromStrings(['wss://spam.example.com']),
);

$note = parseEvent(['id' => str_repeat('4', 64), 'kind' => 1, 'pubkey' => $alice, 'created_at' => 1700000100,
    'tags' => [['p', $bob], ['p', $carol, 'wss://carol-hint.example.com']]]);

echo "Alice replies to Bob and Carol (kind 1):\n";
echo '  '.describe(PublishRouter::route($note, $directory))."\n";
$missing = RecipientsWithoutInboxFinder::find($note, $directory)->toArray();
echo '  recipients without an inbox: '.implode(', ', array_map(static fn (PublicKey $pk): string => $names[$pk->toHex()], $missing))."\n";

$followList = parseEvent(['id' => str_repeat('6', 64), 'kind' => 3, 'pubkey' => $alice, 'created_at' => 1700000150,
    'tags' => [['p', $bob], ['p', $carol, 'wss://carol-hint.example.com']]]);

echo "\nAlice follows Bob and Carol (kind 3: its p tags are data, not mentions):\n";
echo '  '.describe(PublishRouter::route($followList, $directory))."\n";

$giftWrapTo = static fn (string $recipient): Event => parseEvent(['id' => str_repeat('5', 64), 'kind' => 1059,
    'pubkey' => str_repeat('e', 64), 'created_at' => 1700000200, 'tags' => [['p', $recipient]]]);

echo "\nA gift-wrapped DM (kind 1059):\n";
echo '  to bob:   '.describe(PublishRouter::route($giftWrapTo($bob), $directory))."\n";
echo '  to carol: '.describe(PublishRouter::route($giftWrapTo($carol), $directory))."\n";

echo "\nAlice reads her general feed:\n";
echo '  '.describe(ReadRouter::route(new FilterCollection([new Filter([1])]), $directory, new ReadPolicy(pubkey($alice))))."\n";

echo "\nAlice reads replies to Bob (#p bob):\n";
$repliesToBob = new Filter([1], new PublicKeyCollection([pubkey($bob)]));
echo '  '.describe(ReadRouter::route(new FilterCollection([$repliesToBob]), $directory, new ReadPolicy(pubkey($alice))))."\n";

echo "\nReading notes by alice, bob and carol (one relay each):\n";
$routes = AuthorReadRouter::route(
    new PublicKeyCollection([pubkey($alice), pubkey($bob), pubkey($carol)]),
    $directory,
    new AuthorReadPolicy(RelaySet::fromStrings(['wss://fallback.example.com']), new PositiveCount(200), new Redundancy(1)),
);
foreach ($routes as $route) {
    $authors = array_merge(...array_map(static fn (PublicKeyCollection $chunk): array => $chunk->toArray(), $route->getAuthorChunks()));
    echo '  '.implode(', ', $route->getRelays()->toStrings()).' <- '.implode(', ', array_map(static fn (PublicKey $pk): string => $names[$pk->toHex()], $authors))."\n";
}

echo "\nRelay hint for a p tag to bob, or an e tag to a note by bob: ".RelayHintSelector::select(pubkey($alice), new RelayHintTarget(pubkey($bob)), $directory)."\n";
$seenOn = RelayUrl::tryFromString('wss://seen.example.com') ?? throw new InvalidArgumentException('not a valid relay URL');
echo 'Relay hint for an e tag to a note by bob, seen on '.$seenOn.': '.RelayHintSelector::select(pubkey($alice), new RelayHintTarget(pubkey($bob), $seenOn), $directory)."\n";
