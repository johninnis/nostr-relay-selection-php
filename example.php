<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorRelaySelector;
use Innis\Nostr\RelaySelection\Domain\Service\PublishRouter;
use Innis\Nostr\RelaySelection\Domain\Service\RelayHintSelector;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorReadRouteContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorRelaysContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\PublishContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\RelayHintContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;

/**
 * @return array{name: string, pubkey: PublicKey, events: list<Event>}
 */
function loadAuthor(string $path): array
{
    $contents = file_get_contents($path);
    if (false === $contents) {
        throw new RuntimeException('Failed to read: '.$path);
    }
    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Fixture is not a JSON object: '.$path);
    }

    $name = $data['name'] ?? null;
    $pubkeyHex = $data['pubkey'] ?? null;
    $rawEvents = $data['events'] ?? null;
    if (!is_string($name) || !is_string($pubkeyHex) || !is_array($rawEvents)) {
        throw new RuntimeException('Malformed fixture: '.$path);
    }

    $events = array_values(array_map(
        static function (mixed $raw) use ($path): Event {
            if (!is_array($raw)) {
                throw new RuntimeException('Event entry is not a JSON object in: '.$path);
            }

            return Event::tryFromRaw($raw) ?? throw new RuntimeException('Invalid event in: '.$path);
        },
        $rawEvents,
    ));

    return [
        'name' => $name,
        'pubkey' => PublicKey::tryFromHex($pubkeyHex) ?? throw new RuntimeException('Invalid pubkey'),
        'events' => $events,
    ];
}

function relayUrl(string $url): RelayUrl
{
    return RelayUrl::tryFromString($url) ?? throw new RuntimeException('Invalid relay URL: '.$url);
}

function printSection(string $title): void
{
    echo "\n=== {$title} ===\n\n";
}

/**
 * @param ?list<RelayUrl> $relays
 */
function printRelays(string $label, ?array $relays): void
{
    if (null === $relays) {
        echo "  {$label}: (null, no fallback)\n";

        return;
    }
    if ([] === $relays) {
        echo "  {$label}: (none)\n";

        return;
    }
    echo "  {$label}:\n";
    foreach ($relays as $url) {
        echo "    - {$url}\n";
    }
}

/**
 * @param array<string, array{name: string, pubkey: PublicKey, events: list<Event>}> $authors
 */
function authorName(PublicKey $pubkey, array $authors): string
{
    foreach ($authors as $author) {
        if ($author['pubkey']->equals($pubkey)) {
            return $author['name'];
        }
    }

    return substr($pubkey->toHex(), 0, 8).'...';
}

$paths = glob(__DIR__.'/tests/corpus/real-world/*.json');
if (false === $paths) {
    throw new RuntimeException('Failed to read real-world fixtures');
}
sort($paths);

/** @var array<string, array{name: string, pubkey: PublicKey, events: list<Event>}> $authors */
$authors = [];
/** @var list<Event> $allRelayListEvents */
$allRelayListEvents = [];
foreach ($paths as $path) {
    $author = loadAuthor($path);
    $authors[$author['name']] = $author;
    $allRelayListEvents = array_merge($allRelayListEvents, $author['events']);
}

printSection('Per-author relay extraction (AuthorRelaySelector)');

foreach ($authors as $name => $author) {
    echo "{$name} ".substr($author['pubkey']->toHex(), 0, 8)."...\n";
    $context = new AuthorRelaysContext($author['pubkey'], $author['events']);
    printRelays('inbox', AuthorRelaySelector::inbox($context));
    printRelays('outbox', AuthorRelaySelector::outbox($context));
    printRelays('dm', AuthorRelaySelector::dm($context));
    echo "\n";
}

printSection('Publish routing (PublishRouter)');
echo "Derek posts a kind 1 note p-tagging fiatjaf and PABLOF7z.\n";
echo "Result fans out across Derek's outbox + recipient inboxes (capped at 3 per recipient).\n";

$kind1 = new Event(
    EventKind::ShortNote->value,
    $authors['Derek Ross']['pubkey'],
    time(),
    [
        new Tag(['p', $authors['fiatjaf']['pubkey']->toHex()]),
        new Tag(['p', $authors['PABLOF7z']['pubkey']->toHex()]),
    ],
);

$publishContext = new PublishContext(
    $authors['Derek Ross']['pubkey'],
    $allRelayListEvents,
    [],
    [],
);

$publishRoute = PublishRouter::route($kind1, $publishContext);
echo "  branch: {$publishRoute->getBranch()->value}\n";
printRelays('publish to', $publishRoute->getRelays());

printSection('Author-set-cover routing (AuthorReadRouter)');
echo "Reading notes from all four authors. Greedy set-cover picks the\n";
echo "smallest number of relays that reach every author with the configured\n";
echo "redundancy (default 3).\n\n";

$readContext = new AuthorReadRouteContext(
    array_values(array_map(static fn (array $a) => $a['pubkey'], $authors)),
    $allRelayListEvents,
    [relayUrl('wss://relay.damus.io'), relayUrl('wss://nos.lol')],
);

$routes = AuthorReadRouter::route($readContext);
foreach ($routes as $i => $route) {
    $relayLine = implode(', ', array_map(static fn (RelayUrl $r) => (string) $r, $route->getRelays()));
    echo '  Route '.($i + 1).":\n";
    echo "    relays: {$relayLine}\n";
    foreach ($route->getAuthorChunks() as $chunk) {
        $names = array_map(static fn (PublicKey $pk) => authorName($pk, $authors), $chunk);
        echo '    authors: '.implode(', ', $names)."\n";
    }
    echo "\n";
}

printSection('Relay hint selection (RelayHintSelector)');
echo "Derek wants to reference a fiatjaf post via an e-tag. Which relay URL\n";
echo "to attach as the hint? The lib prefers the intersection of Derek's\n";
echo "outbox with fiatjaf's inbox, falling back to either side's first relay.\n\n";

$hint = RelayHintSelector::select(new RelayHintContext(
    $authors['fiatjaf']['pubkey'],
    $authors['Derek Ross']['pubkey'],
    $allRelayListEvents,
));

echo '  hint: '.(null !== $hint ? (string) $hint : '(none)')."\n";
