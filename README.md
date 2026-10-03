# Nostr Relay Selection

[![CI](https://github.com/johninnis/nostr-relay-selection-php/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/nostr-relay-selection-php/actions/workflows/ci.yml)

Deterministic outbox-model relay routing for Nostr in PHP: publish routing, read routing, author set cover, relay hints, zap-request relays and URL classification, as pure functions with zero runtime dependencies. A routing policy locked to a shared JSON corpus, not an engine.

## Why this library?

When a client publishes a reply, which relays should it send to? When it reads an author's notes, which relays hold them? Which relay hint will work for the person a tag points at? This library answers those questions from the relay lists people publish (NIP-65, NIP-17, NIP-51), deterministically: the same inputs always give the same relays in the same order. It opens no connections, keeps no state and ships no default relays; your relay pool and your fallbacks wrap it. Why it is built that way is recorded in [ADR-0006](docs/adr/0006-the-library-is-a-routing-policy-not-an-engine.md).

The TypeScript port, [`@innis/nostr-relay-selection`](https://jsr.io/@innis/nostr-relay-selection), implements the same policy against the same corpus.

## Requirements

- PHP 8.4 or higher
- No extensions, system libraries or Composer dependencies

## Installation

```bash
composer require innis/nostr-relay-selection
```

## Quick start

```php
use Innis\Nostr\RelaySelection\Domain\Collection\EventCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Failure\NoDmRelaysFailure;
use Innis\Nostr\RelaySelection\Domain\Service\PublishRouter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;

$relayLists = new EventCollection(array_filter(array_map(Event::tryFromRaw(...), $rawRelayListArrays)));
$directory = RelayDirectory::fromEvents($relayLists, RelaySet::fromStrings(['wss://spam.example.com']));

$note = Event::tryFromRaw($rawNoteArray);
if (null !== $note) {
    $route = PublishRouter::route($note, $directory);
    if (!$route instanceof NoDmRelaysFailure) {
        foreach ($route->getRelays() as $relay) {
            $pool->publish((string) $relay, $note);
        }
    }
}
```

[`examples/route-relays.php`](examples/route-relays.php) is a self-contained, runnable walk through every operation with three synthetic identities:

```bash
php examples/route-relays.php
```

## Types and validation

Every input type has a parser for untrusted, JSON-shaped data that returns `null` when the input is malformed:

```php
$event  = Event::tryFromRaw($array);          // ?Event: id, kind, pubkey, created_at, tags
$filter = Filter::tryFromRaw($array);         // ?Filter: kinds, #p, search (other fields ignored)
$tag    = Tag::tryFromRaw(['p', $hex]);       // ?Tag
$pubkey = PublicKey::tryFromHex($hex);        // ?PublicKey
$id     = EventId::tryFromHex($hex);          // ?EventId
$relay  = RelayUrl::tryFromString($url);      // ?RelayUrl
$relays = RelaySet::fromStrings($urls);       // RelaySet, malformed entries dropped
```

Collections are typed and immutable: `EventCollection`, `FilterCollection`, `PublicKeyCollection`, `TagCollection` and `RelaySet` reject an element of the wrong type with `InvalidArgumentException`. `RelaySet` also deduplicates by canonical URL on construction and offers `union`, `without`, `take`, `contains`, `first` and `toStrings`.

`Event` carries the five fields routing reads. The id is used only to break a `created_at` tie between two relay lists: the lowest id wins, as NIP-01 specifies ([ADR-0004](docs/adr/0004-the-event-value-carries-the-five-fields-routing-reads.md)).

## The relay directory

Every routing operation reads relay lists through one `RelayDirectory`, built once from the relay-list events you hold and your blocklist:

```php
$directory = RelayDirectory::fromEvents($relayListEvents, $blockedRelays);

$directory->relaysOf($pubkey, RelayRole::Outbox);  // RelaySet
$directory->permitted($callerRelays, $extraRelays); // union of the sets, minus blocked relays
$directory->getBlocked();                           // the blocklist
```

- For each (pubkey, kind) the directory keeps the newest event: the greatest `created_at`, then the lowest id. The result does not depend on the order of the events.
- A `RelayRole` names what a list is for and owns the kind it is read from:

  | Role | Kind | Tags read |
  | --- | --- | --- |
  | `RelayRole::Inbox` | 10002 (NIP-65) | `r` tags not marked `write` (unmarked, `read`, `both` or any other marker), a relay named twice being the union ([ADR-0016](docs/adr/0016-a-relays-nip65-marker-is-read-across-every-r-tag-naming-it-and-an-undefined-marker.md)) |
  | `RelayRole::Outbox` | 10002 (NIP-65) | `r` tags not marked `read` (unmarked, `write`, `both` or any other marker), a relay named twice being the union |
  | `RelayRole::Dm` | 10050 (NIP-17) | `relay` tags |
  | `RelayRole::Search` | 10007 (NIP-51) | `relay` tags |
  | `RelayRole::Blocked` | 10006 (NIP-51) | `relay` tags |

- Every relay set the directory returns is deduplicated and has the blocklist removed, so no routing operation ever chooses a blocked relay.

The blocklist is passed in rather than read from a kind 10006 event because NIP-51 lets entries live in encrypted content, which this library cannot decrypt. Read the public entries with `RelayRole::Blocked->extract($event->getTags())`, add any you decrypted, and pass the result.

## Route a publish

`PublishRouter::route($event, $directory, $policy)` returns a `PublishRoute` (`getBranch()`, `getRelays()`) or, for a gift wrap with nowhere to go, `NoDmRelaysFailure::NoDmRelays`. The branches are tried in this order:

| Branch | When | Relays |
| --- | --- | --- |
| `PublishBranch::Dm` | kind 1059 or 21059 (NIP-59 gift wrap, stored or ephemeral) | Every `p`-tagged recipient's kind 10050 relays. None left: `NoDmRelaysFailure` (NIP-17: do not publish; there is no fallback). |
| `PublishBranch::Draft` | kinds 30024, 30403, 31234 | `privateContentRelays`, or the author's outbox when none remains after blocking. |
| `PublishBranch::Group` | an `h` tag with a group id (NIP-29) | The `h` tag's relay hint and `groupRelays`; no outbox or inbox fan-out. When none remains after blocking, the event falls through to `General`. |
| `PublishBranch::General` | everything else | The author's outbox; plus every inbox relay of each `p`-tagged recipient (NIP-65), or, as a best-effort fallback, the `p` tag's relay hint when the recipient has no inbox; plus `indexerRelays` for indexed kinds (0, 3, 10002, 10050). `perRecipientCap` limits the inbox relays per recipient; by default (`PerRecipientCap::all()`) there is no limit. A kind whose `p` tags are data, not mentions, has no recipients and goes to the author's outbox (and indexers) only: kind 3 (NIP-02 follow list), 1984 (NIP-56 report) and the NIP-51 lists and sets of people, 10000, 10017, 10020, 10054, 10064, 10101, 30000, 30007, 39089 and 39092 (`EventKind::isPubkeyData`). Every other kind fans out ([ADR-0018](docs/adr/0018-a-public-event-is-also-sent-to-every-inbox-relay-of-each-user-it-tags.md)). |

The outbox is always the event author's. Blocked relays are removed before any choice: a recipient's inbox is deduplicated and cleared of blocked relays before the cap is taken, a blocked hint is ignored, and a draft or group whose relays are all blocked falls back as above ([ADR-0010](docs/adr/0010-blocked-relays-are-removed-before-any-choice-is-made.md)). DM and draft branches come before the group branch, so private content never reaches a group relay ([ADR-0011](docs/adr/0011-private-branches-take-precedence-over-group-routing.md)), and no DM, draft or group message fans out to a tagged user's inbox.

NIP-65 also asks that the author's kind 10002 be sent to every relay the event was published to. That is your job: publish the author's relay list event to the same route.

```php
$route = PublishRouter::route($event, $directory, new PublishPolicy(
    privateContentRelays: $decryptedKind10013Relays,
    indexerRelays: RelaySet::fromStrings(['wss://purplepag.es']),
    groupRelays: $relaysHostingThisGroup,
    perRecipientCap: new PerRecipientCap(3), // optional; default PerRecipientCap::all()
));
$strategy = $route instanceof NoDmRelaysFailure ? 'do not publish' : match ($route->getBranch()) {
    PublishBranch::General => 'NIP-65 outbox and inbox fan-out',
    PublishBranch::Dm => 'NIP-17 DM relays',
    PublishBranch::Draft => 'private content relays',
    PublishBranch::Group => 'NIP-29 group relays',
};
```

A non-DM route may carry an empty `RelaySet` when your inputs name no relay (the author has no outbox and you supplied nothing); what to fall back to is your decision.

## Route a read

`ReadRouter::route($filters, $directory, new ReadPolicy($userPubkey, $callerRelays))` returns a `ReadRoute` or `NoDmRelaysFailure`:

| Branch | When | Relays |
| --- | --- | --- |
| `ReadBranch::Search` | any filter has a non-empty `search` | The user's kind 10007 search relays, then the caller relays. |
| `ReadBranch::DmInbox` | every filter asks only for kinds 1059 and/or 21059 with exactly one `#p`, the same recipient in each | The recipient's kind 10050 relays. None left: `NoDmRelaysFailure`. |
| `ReadBranch::General` | anything else, including no filters | The inbox relays of every user a `#p` names (NIP-65: events about a user are read from that user's read relays), then the user's inbox and outbox, then the caller relays. The user's own relays are read only when no filter has a `#p`, a filter has none, or a tagged user has no inbox left after blocking ([ADR-0019](docs/adr/0019-a-read-about-tagged-users-goes-to-their-inbox-relays.md)). |

`FilterPatternClassifier::classify($filters)` exposes the classification on its own as a `FilterPattern`, with the recipient available from `getDmRecipient()`.

## Read many authors

`AuthorReadRouter::route($authors, $directory, $policy)` plans which outbox relays to read a set of authors from. Greedy set cover groups authors who share a relay; each author is read from up to the redundancy target; each route's authors are chunked. Authors with no usable outbox (no kind 10002, no write entries, or all blocked) are read from the fallback relays in a final route, which is omitted when no fallback relay remains. No route ever has an empty relay set.

```php
$routes = AuthorReadRouter::route($followed, $directory, new AuthorReadPolicy(
    fallbackRelays: $defaultRelays,
    maxAuthorsPerFilter: new PositiveCount(200),
    redundancy: new Redundancy(3),      // or Redundancy::all()
));
foreach ($routes as $route) {
    foreach ($route->getAuthorChunks() as $chunk) {
        $pool->subscribe($route->getRelays()->toStrings(), ['kinds' => [1], 'authors' => array_map(fn ($pk) => $pk->toHex(), $chunk->toArray())]);
    }
}
```

`PositiveCount`, `PerRecipientCap` and `Redundancy` throw `InvalidArgumentException` for a count below one ([ADR-0013](docs/adr/0013-invalid-routing-counts-are-programmer-errors.md)).

## Other operations

| Operation | Purpose |
| --- | --- |
| `RelayHintSelector::select($userPubkey, $target, $directory)` | One relay hint for a tag, else `null`: a relay where the target's events are found ([ADR-0020](docs/adr/0020-a-relay-hint-names-where-its-target-is-found-and-prefers-the-relay-it-was-seen-on.md)). `$target` is `new RelayHintTarget($pubkey, $seenOn = null)`: `$pubkey` is the referenced event's author for an `e` / `q` / `a` tag and the tagged user for a `p` tag; `$seenOn` is a relay you received the target from (the event, or an event by the tagged user). The hint is `$seenOn` unless blocked, else the target's first outbox relay, else the user's first inbox relay. |
| `ZapRequestRelaySelector::select($zapperPubkey, $recipientPubkey, $directory)` | NIP-57: the zapper's inbox relays, then the recipient's. |
| `RecipientsWithoutInboxFinder::find($event, $directory)` | The `p`-tagged recipients publish routing cannot reach through an inbox (no kind 10002, no read entries, or all blocked), for which `PublishBranch::General` falls back to the `p`-tag hint. Useful for fetching missing relay lists before publishing. Empty for gift wrap and draft kinds and for kinds whose `p` tags are data, which never fan out. |
| `RecipientExtractor::fromEvent($event)` | The event's `p`-tagged recipients with their relay hints, first occurrence of each pubkey; none for a kind whose `p` tags are data. |
| `RelayRole::extract($tags)` / `RelayRole::kind()` | Read one role's relays from a relay-list event's tags; the kind a role is read from. |
| `RelayUrl::isOnion` / `isLoopback` / `isLocalAddr` / `isInsecure` | URL predicates for your own filtering; routing never applies them. Address ranges match IPv4 dotted quads only, so `wss://10.example.com` is not local; no relay URL holds an IPv6 literal ([ADR-0017](docs/adr/0017-the-url-classifiers-read-only-names-and-ipv4-addresses-because-no-relay-url-holds.md)). |
| `EventKind::isGiftWrap` / `isDraft` / `isIndexed` / `isPubkeyData` | The kind groupings routing branches on. `EventKind` names only the kinds routing distinguishes ([ADR-0014](docs/adr/0014-a-kind-is-named-only-when-routing-branches-on-it.md)). |

## Caller-owned lists

Three lists can be partly or wholly encrypted, so you pass their relays in rather than the library reading events:

| Kind | NIP | Where it goes |
| --- | --- | --- |
| 10006 blocked relays | NIP-51 | `RelayDirectory::fromEvents($events, $blocked)`; removed from everything. |
| 10007 search relays | NIP-51 | Public entries are read from the directory for the search branch; add decrypted entries to the read policy's caller relays. |
| 10013 private content relays | NIP-37 | The `privateContentRelays` argument of `PublishPolicy`, for drafts. |

## Upgrading from 0.2

0.3 replaces the context objects with the directory and small policy objects:

- Build `RelayDirectory::fromEvents($events, $blocked)` once and pass it to every call. `PublishRouter::route($event, $context)` becomes `route($event, $directory, $policy)`; `ReadRouter::route($context)` becomes `route($filters, $directory, $policy)`; `AuthorReadRouter::route($context)` becomes `route($authors, $directory, $policy)`; `RelayHintSelector::select($context)` becomes `select($userPubkey, $target, $directory)` (see below) and `ZapRequestRelaySelector` takes pubkeys then the directory; `MissingRelayListFinder` becomes `RecipientsWithoutInboxFinder`.
- `AuthorRelaySelector`, `EventSelector`, `RelayListExtractor` and `RelaySetBuilder` are gone: use `$directory->relaysOf()`, `RelayRole::extract()` and `RelaySet`.
- Routes never have `null` relays. A refused DM is `NoDmRelaysFailure::NoDmRelays`; test with `$route instanceof NoDmRelaysFailure`. `AuthorReadRouter` returns an `AuthorReadRouteCollection`.
- `Event`, `Filter` and `Tag` moved to `Domain\ValueObject\Protocol`; `Event` requires an id; lists are typed collections; `PublicKey` has no `__toString` (use `toHex()`); `Filter::getSearch()` is gone; `EventKind` predicates are enum methods.
- Counts are `PositiveCount` / `PerRecipientCap` / `Redundancy` value objects; `null` redundancy becomes `Redundancy::all()`.
- The publish outbox is the event author's (`$event->getPubkey()`), not a caller-supplied user key: `PublishContext::$userPubkey` is gone ([ADR-0018](docs/adr/0018-a-public-event-is-also-sent-to-every-inbox-relay-of-each-user-it-tags.md)). The read branch derives the user's relays from the directory.
- Publish routing gains NIP-29 group routing: an `h`-tagged event whose group relays (the tag's relay hint and `PublishPolicy`'s group relays) are not all blocked routes as `PublishBranch::Group` to those relays only, after the DM and draft branches, so an `h`-tagged gift wrap or draft is never sent to a group relay ([ADR-0011](docs/adr/0011-private-branches-take-precedence-over-group-routing.md)). A `match` over `PublishBranch` needs the new case.
- Kinds take nostr-core's names: `EventKind::ProfileMetadata` becomes `Metadata`, `BlockedRelayList` `BlockedRelaysList`, `SearchRelayList` `SearchRelaysList` and `LongformDraft` `LongformContentDraft` ([ADR-0014](docs/adr/0014-a-kind-is-named-only-when-routing-branches-on-it.md)).
- An `r` tag with a marker other than `read` or `write` now counts as both instead of being dropped, and a relay named by several `r` tags is the union of them ([ADR-0016](docs/adr/0016-a-relays-nip65-marker-is-read-across-every-r-tag-naming-it-and-an-undefined-marker.md)).
- Kind 21059 (the NIP-59 ephemeral gift wrap) is routed like kind 1059: published to the recipients' kind 10050 relays or refused, and a read asking only for gift-wrap kinds with one `#p` is a `ReadBranch::DmInbox` read. `FilterPatternClassifier::classify` returns a `FilterPattern` instead of a `ReadBranch`, and `sharedGiftWrapRecipient` is gone (use `getDmRecipient()`).
- Blocked relays are removed before any choice is made, so a blocklist can change the branch, not only the relays: a recipient's inbox is deduplicated and cleared of blocked relays before the cap, a recipient whose inbox is all blocked falls back to the `p`-tag hint, and a draft or group whose relays are all blocked falls back ([ADR-0010](docs/adr/0010-blocked-relays-are-removed-before-any-choice-is-made.md)). `AuthorReadRouter` omits the fallback route when no fallback relay remains instead of returning one with no relays ([ADR-0012](docs/adr/0012-only-a-dm-route-can-be-refused-and-a-refusal-is-a-value.md)).
- `RecipientsWithoutInboxFinder` reports every recipient publish routing cannot reach through an inbox (no kind 10002, a list with no read entries, or every inbox relay blocked), where `MissingRelayListFinder` reported only those with no kind 10002.
- `RelayUrl::isLoopback` and `isLocalAddr` match address ranges on dotted-quad IPv4 hosts only: a hostname such as `127.example.com` or `10.example.com` is no longer loopback or local.
- `Event::tryFromRaw`, `Filter::tryFromRaw` and `Tag::tryFromRaw` refuse an array where the protocol has the other JSON type: a filter given as a non-empty list, `kinds`, `#p`, `tags` or a tag given as a keyed array.
- Publish fan-out follows NIP-65: on `PublishBranch::General` an event of any kind that mentions users goes to every inbox relay of each `p`-tagged recipient. The kinds whose `p` tags are data, not mentions, still go to the author's outbox only, as they did in 0.2 where `EventKind::isInboxFanout` did not list them: kind 3, kind 1984 and the NIP-51 lists and sets of people (10000, 10017, 10020, 10054, 10064, 10101, 30000, 30007, 39089, 39092). `EventKind::isPubkeyData` names them, with the new cases `Reporting`, `MuteList`, `GitAuthorsList`, `MediaFollowsList`, `FavouritePodcastsList`, `AuthoredPodcastsList`, `GoodWikiAuthorsList`, `FollowSet`, `KindMuteSet`, `StarterPack` and `MediaStarterPack`; a `match` over `EventKind` needs them. The per-recipient cap is `PublishPolicy`'s `PerRecipientCap`, defaulting to `PerRecipientCap::all()` where `PublishContext` defaulted to 3 (pass `new PerRecipientCap(3)` to keep a cap), and `PublishContext::DEFAULT_PER_RECIPIENT_CAP` is gone. `EventKind::isInboxFanout` and the cases it alone needed (`ShortNote`, `Repost`, `Reaction`, `GenericRepost`, `PublicMessage`, `Comment`, `Highlight`) are removed; take kinds from `innis/nostr-core`. `RecipientsWithoutInboxFinder` now reports recipients for every kind except gift wraps, drafts and the kinds whose `p` tags are data ([ADR-0018](docs/adr/0018-a-public-event-is-also-sent-to-every-inbox-relay-of-each-user-it-tags.md)).
- A `ReadBranch::General` read whose filters name users in `#p` reads those users' inbox relays, and the user's own relays only when a filter has no `#p` or a tagged user has no inbox ([ADR-0019](docs/adr/0019-a-read-about-tagged-users-goes-to-their-inbox-relays.md)).
- Sending the author's kind 10002 to the relays an event went to (NIP-65) is the caller's job.
- `RelayUrl::isLoopback` no longer names `::1`: no relay URL can hold an IPv6 literal, so it never matched ([ADR-0017](docs/adr/0017-the-url-classifiers-read-only-names-and-ipv4-addresses-because-no-relay-url-holds.md)).
- `RelayHintSelector::select`'s `$target` is `new RelayHintTarget($pubkey, $seenOn = null)`, and every hint names a relay where its target's events are found: `$seenOn` (a relay you received the event, or an event by the tagged user, from), then the target's outbox, then the user's inbox, where 0.2 took the user's outbox relay in the target's inbox, then the target's inbox, then the user's outbox. `$pubkey` is the referenced event's author for an `e` / `q` / `a` tag and the tagged user for a `p` tag ([ADR-0020](docs/adr/0020-a-relay-hint-names-where-its-target-is-found-and-prefers-the-relay-it-was-seen-on.md)).

## Testing

```bash
composer test          # PHPUnit (Unit, Compliance, Acceptance) and PHPStan level 9
composer test-unit     # Unit suite only
composer analyse       # PHPStan level 9
composer check-style   # php-cs-fixer, dry run
composer check-rector  # Rector, dry run
composer fix-style     # php-cs-fixer
```

The corpus under [`tests/corpus/`](tests/corpus/) is the specification. It is a byte-identical copy of the TypeScript repository's corpus, and a pinned digest fails the build if the two drift ([ADR-0007](docs/adr/0007-the-corpus-is-the-specification-and-its-digest-is-pinned.md)). Its README describes every vector format.

## Architecture decisions

Design rationale, including the choices that read like smells until you know why (why the protocol types are re-declared instead of depending on `innis/nostr-core`, why the routing operations are static), lives in [`docs/adr/`](docs/adr/). Read it before changing the code.

## License

MIT License. See LICENSE file for details.
