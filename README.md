# Nostr Relay Selection

[![CI](https://github.com/johninnis/nostr-relay-selection-php/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/nostr-relay-selection-php/actions/workflows/ci.yml)

A PHP library for routing Nostr events to and from relays. Implements outbox-model publish routing, read routing, author-set-cover, relay hint selection, NIP-65 inbox/outbox/DM list parsing, NIP-17 DM-inbox handling, and URL classification — as pure functions, with zero runtime dependencies.

## Why this library?

When a client publishes a kind 1 reply, *which* relays should it actually send to? When it queries an author's notes, which relays will return them? When it picks a relay hint for an `e` tag, which one will work for the recipient? These are not trivial questions in the outbox model.

`innis/nostr-relay-selection` answers them as pure functions over the user's relay-list events. It does not open WebSocket connections, does not depend on a relay pool, does not depend on `innis/nostr-core`, and does not depend on any other Nostr library. Feed it events and a context, get back a deterministic list of relays.

## Design reasoning: a spec, not an engine

The Nostr ecosystem already has several relay-selection implementations — NDK's `OutboxTracker`, rust-nostr's `gossip` crate, go-nostr's `sdk` hints DB, Coracle's `welshman/router`. They are all *engines*: stateful, heuristic, async, coupled to a pool and to learned data. They make pragmatic, useful tradeoffs, and none of them are deterministic.

This library makes the opposite tradeoff. It is a *policy specification*:

- **Pure functions.** Every operation is a stateless static method. Same inputs, same outputs. No I/O, no time, no randomness.
- **Deterministic by construction.** No `Math.random()` tie-breaks (welshman), no time-decay scoring (go-nostr), no `received_events` counters (rust-nostr), no batch-popularity sort (NDK), no hardcoded fallback URLs.
- **NIP-derived behaviour only.** Every routing decision is grounded in a NIP — NIP-65 for kind 10002, NIP-17 for kind 10050, NIP-57 for zap requests, NIP-50 for search relays. No empirical heuristics that drift from the spec.
- **Zero runtime dependencies.** Requires only PHP 8.4. Suitable for embedding in any Nostr client, relay, indexer, or back-end without dragging in transport, crypto, caches, or framework code.
- **Clean Architecture, domain-only.** All logic lives in the Domain layer. There is no Application or Infrastructure layer because there is nothing external to coordinate.
- **Behaviour locked to a JSON corpus.** The test vectors under `tests/corpus/` are the spec. Any implementation in any language that passes every vector is conformant. The PHP and TypeScript ports share the corpus; a Go, Kotlin, or Rust port would too.

Engines and specs compose. This library is the policy; an engine wraps it with caching, pool state, fallbacks, scoring, or whatever else a runtime needs. The two layers stay separate so the policy stays portable and auditable.

## Requirements

- PHP 8.4 or higher

No PHP extensions are required. No system libraries. No Composer dependencies.

## Installation

```bash
composer require innis/nostr-relay-selection
```

## Quick Start

All services are pure static methods. Inputs are typed context objects; outputs are typed route objects (with a branch enum + a list of `RelayUrl`) or lists of `RelayUrl` directly.

For a runnable end-to-end demonstration against live relay-list events from four real Nostr identities (loaded from `tests/corpus/real-world/`), see [`example.php`](example.php):

```bash
php example.php
```

### Constructing typed objects from raw JSON

Every type the routing services consume has a static `fromRaw(mixed): ?self` factory that validates the JSON-shaped input and returns `null` on malformed data:

```php
use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Entity\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;

$event  = Event::tryFromRaw(json_decode($rawJson, true));   // ?Event
$filter = Filter::tryFromRaw(['kinds' => [1], 'search' => 'hi']);  // ?Filter
$tag    = Tag::tryFromRaw(['p', $pubkeyHex]);               // ?Tag
$pubkey = PublicKey::tryFromHex($hex);                      // ?PublicKey
$relay  = RelayUrl::tryFromString($url);                    // ?RelayUrl
```

Use these at your application's adapter boundary. The lib never returns `null` from happy-path routing — `null` from `fromRaw`/`fromHex`/`fromString` always means "the input you gave me was not a valid X."

### Route a publish

Given an event and the user's relay-list events (kind 10002 / 10050), decide which relays to publish to. Returns a `PublishRoute` whose `getBranch()` reports the policy applied and whose `getRelays()` lists the targets (which may be empty, or `null` for the `Dm` branch — see below).

- `PublishBranch::General` — NIP-65 outbox routing for kind 1/6/7/16/24/1111/9802 etc. Fans out to recipient inboxes (capped per recipient). Adds `indexerRelays` for indexed kinds (0, 3, 10002, 10050) — the events indexers like `purplepag.es` harvest.
- `PublishBranch::Dm` — NIP-17 routing for kind 1059 gift-wraps. Targets recipients' kind-10050 inbox relays only. **Returns `Dm` with `relays = null` if no recipient has a kind 10050 (or every DM relay is blocked)** — per NIP-17, clients SHOULD NOT publish if the recipient has not signalled readiness. There is no fallback.
- `PublishBranch::Draft` — kinds 30024, 30403, 31234. Routes to caller-supplied `privateContentRelays` if any; otherwise falls back to the user's outbox.

```php
use Innis\Nostr\RelaySelection\Domain\Service\PublishRouter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\PublishContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\PublishBranch;

$context = new PublishContext(
    userPubkey: PublicKey::tryFromHex($userHex) ?? throw new InvalidArgumentException('bad pubkey'),
    relayListEvents: $cachedKind10002And10050Events,
    privateContentRelays: [],   // caller's pre-extracted kind 10013 URLs (see NIP-37 note below)
    indexerRelays: [],
    blockedRelays: $callerBlockedRelays,   // caller's pre-extracted kind 10006 URLs
);

$route = PublishRouter::route($event, $context);
$strategy = match ($route->getBranch()) {
    PublishBranch::General => 'NIP-65 outbox fan-out',
    PublishBranch::Dm      => 'NIP-17 DM inboxes (relays is null if the recipient has no kind 10050)',
    PublishBranch::Draft   => 'private content relays, falling back to the user outbox',
};
foreach ($route->getRelays() ?? [] as $relay) {
    $pool->publish((string) $relay, $event);
}
```

The lib does **not** implement NIP-37 itself — kind 10013's relay list is NIP-44-encrypted inside `content`, and decryption requires a signer + crypto, both out of scope here. Callers that want NIP-37-aware draft routing must fetch and decrypt kind 10013 themselves and pass the resulting URLs into `PublishContext::$privateContentRelays`.

### Route a read

Given a set of filters, decide which relays to subscribe to. Returns a `ReadRoute` with a `ReadBranch` enum and a list of relays.

- `ReadBranch::Search` — any filter with a `search` field. Unions caller-supplied `searchRelays` (from the user's kind 10007 list) with `callerRelays`.
- `ReadBranch::DmInbox` — every filter is `{kinds: [1059], #p: [singleRecipient]}` and the recipient matches across filters. Targets the recipient's kind-10050 inbox relays.
- `ReadBranch::General` — everything else. Unions the user's relays with `callerRelays`.

```php
use Innis\Nostr\RelaySelection\Domain\Entity\Filter;
use Innis\Nostr\RelaySelection\Domain\Service\ReadRouter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\ReadContext;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;

$context = new ReadContext(
    userRelayUrls: $userOutboxRelays,
    callerRelays: $relaysPassedToTheQuery,
    filters: [new Filter(kinds: [1], pTags: null, search: null)],
    relayListEvents: $cachedRelayLists,
    blockedRelays: $callerBlockedRelays,    // pre-extracted kind 10006
    searchRelays: $callerSearchRelays,      // pre-extracted kind 10007
);

$route = ReadRouter::route($context);
$strategy = match ($route->getBranch()) {
    ReadBranch::Search   => 'subscribe to search-capable relays',
    ReadBranch::DmInbox  => "subscribe to the recipient's DM inboxes",
    ReadBranch::General  => 'subscribe to user + caller relays',
};
```

### Filter pattern detection

`ReadRouter` internally calls `FilterPatternClassifier::classify($filters)` to map a filter set to a `ReadBranch` (`Search`, `DmInbox`, `General`). The primitive is exposed so callers can inspect or branch on the pattern without invoking the full router.

```php
use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;
use Innis\Nostr\RelaySelection\Domain\Service\FilterPatternClassifier;

$branch = FilterPatternClassifier::classify($filters);
// ReadBranch::Search | ReadBranch::DmInbox | ReadBranch::General
```

### Author read: greedy set cover

Given a list of author pubkeys, decide which outbox relays cover them. Uses greedy set-cover so two authors who share a relay are queried together; chunks each plan if the author count exceeds `maxAuthorsPerFilter`; falls back to caller-supplied relays for authors with no NIP-65 list.

```php
use Innis\Nostr\RelaySelection\Domain\Service\AuthorReadRouter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorReadRouteContext;

$context = new AuthorReadRouteContext(
    authorPubkeys: $followedPubkeys,
    relayListEvents: $cachedRelayLists,
    fallbackRelays: $defaultRelays,
    maxAuthorsPerFilter: 200,
    redundancy: 3,
    blockedRelays: $callerBlockedRelays,
);

foreach (AuthorReadRouter::route($context) as $route) {
    foreach ($route->getAuthorChunks() as $chunk) {
        $pool->subscribe(
            array_map('strval', $route->getRelays()),
            ['kinds' => [1], 'authors' => array_map(fn ($pk) => $pk->toHex(), $chunk)],
        );
    }
}
```

### Pick a relay hint

For `e` / `p` / `q` tags, pick a single relay URL the recipient is likely to read. Prefers the intersection of the user's outbox with the target's inbox, falls back to either side's first relay, returns `null` if neither side has a list.

### URL classification

Pure predicates on `RelayUrl` for use in caller-side filtering. The library does not apply these itself — they're exposed so consumers can compose filters without re-implementing host detection.

```php
$url = RelayUrl::tryFromString('ws://192.168.1.11:7777');
$url->isOnion();      // false
$url->isLoopback();   // false (true for localhost, 127.0.0.0/8, ::1)
$url->isLocalAddr();  // true  (loopback OR RFC1918 OR .local mDNS)
$url->isInsecure();   // true  (ws:// AND not onion)
```

### Caller-owned lists (blocked, search, private content)

Three kinds of user-owned data are passed as pre-extracted URL lists rather than as raw events:

| Kind  | NIP   | Field on context                          | Notes                                                              |
|-------|-------|-------------------------------------------|--------------------------------------------------------------------|
| 10006 | NIP-65| `blockedRelays`  (on every context)       | Subtracted from every route output uniformly.                      |
| 10007 | NIP-50| `searchRelays`  (on `ReadContext`)        | Unioned into the `Search` branch alongside caller-supplied relays. |
| 10013 | NIP-37| `privateContentRelays` (on `PublishContext`)| Encrypted content — caller must decrypt before passing.            |

For 10006 and 10007 the caller pre-extracts URLs from their cached event using `RelayListExtractor::blocked($event->getTags())` or `::search($event->getTags())`. For 10013, the caller does NIP-44 decryption themselves and passes the resulting URLs.

This mirrors the existing `userRelayUrls` pattern on `ReadContext`: user-owned data is the caller's responsibility to extract; library policy is to apply.

### Other operations

| Service                                | Purpose                                                                                                  |
|----------------------------------------|----------------------------------------------------------------------------------------------------------|
| `AuthorRelaySelector::inbox`     | Pick inbox relays for one author (kind 10002 `read`/`both` markers).                                     |
| `AuthorRelaySelector::outbox`    | Pick outbox relays for one author (kind 10002 `write`/`both` markers).                                   |
| `AuthorRelaySelector::dm`        | Pick DM relays for one author (kind 10050 `relay` tags).                                                 |
| `ZapRequestRelaySelector`        | Merge zapper and recipient inbox relays for a zap request.                                               |
| `RelayHintSelector`               | Pick one relay URL hint for an `e`/`p`/`q` tag.                                                          |
| `FilterPatternClassifier::classify`   | Classify a filter set as `Search`, `DmInbox`, or `General`.                                              |
| `FilterPatternClassifier::sharedGiftWrapRecipient` | If every filter is `{kinds: [1059], #p: [singleRecipient]}` with the same recipient, return that `PublicKey`; otherwise `null`. Useful for detecting DM-target reads without invoking the full router. |
| `MissingRelayListFinder`       | For inbox-fanout events, list `p`-tagged pubkeys whose relay list you do not yet have cached.            |
| `EventSelector::newestByPubkeyAndKind` | Find the newest event for a given `(pubkey, kind)` tuple in a heterogeneous event array. Used internally by every routing service and exposed for callers building their own cache layers. Returns `?Event`.|
| `RelayListExtractor::inbox/outbox/dm`  | Parse `r` and `relay` tags from kind 10002 / 10050 events.                                               |
| `RelayListExtractor::blocked/search`   | Parse `relay` tags from kind 10006 / 10007 events.                                                       |
| `RelaySetBuilder::build`               | Merge any number of relay sources into one deduplicated list, preserving first-seen order.               |
| `RelaySetBuilder::subtract`            | Remove URLs in a blocklist from a relay set.                                                             |
| `RelayUrl::tryFromString`                 | Normalise an arbitrary URL string. Lowercases scheme and host, strips default ports and trailing slashes. Rejects non-wss(?), fragments, `%20` in paths, malformed hostnames, out-of-range ports, concatenated URLs, and inputs over 200 chars.|
| `RelayUrl::isOnion/isLoopback/isLocalAddr/isInsecure` | Pure URL classification predicates.                                                       |
| `RelayUrl::equals`                     | Compare two `RelayUrl` instances for canonical-string equality.                                          |
| `Event::tryFromRaw` / `Filter::tryFromRaw` / `Tag::tryFromRaw` | Validate and construct typed objects from JSON-shaped arrays. Return `null` on malformed input.   |
| `PublicKey::tryFromHex` / `PublicKey::toHex` / `PublicKey::equals` | Construct from / serialise to / compare hex pubkeys.                                    |

## Routing rules

The complete policy in one place. Each rule is encoded in the source and locked by a corpus vector.

### What goes in `EventKind`

A kind appears in `EventKind` **if and only if the routing policy distinguishes it from arbitrary unknown kinds.** Concretely, a kind belongs in the enum when at least one of these is true:

- It triggers a branch in `PublishRouter` (a `match` arm or a helper predicate).
- It triggers a branch in `ReadRouter` or `FilterPatternClassifier`.
- It is parsed by `RelayListExtractor` (kind 10002 / 10006 / 10007 / 10050).
- It drives a dedicated service (kinds parsed from the relay-list events array).

The rule is **drives a branch in `PublishRouter::route`**, not "has any routing rule." Two NIPs define routing rules for kinds that are nonetheless absent from `EventKind`, and that's deliberate:

- **`Deletion` (5)** — NIP-09 says deletion events should publish to every relay the original event was on. The lib has no event-publication history and tracking that would require state (out of scope: no I/O, no caches). So kind 5 falls through to General → user's outbox, the best a stateless pure-policy library can offer. Adding a `Deletion` case to `EventKind` would imply a dedicated branch the lib cannot honestly implement.

- **`ZapRequest` (9734)** — NIP-57 routing (zapper-inbox ∪ recipient-inbox) **is** implemented, but as a dedicated service: `ZapRequestRelaySelector::select` over `ZapRequestContext`. The kind doesn't belong in `EventKind` because that enum is specifically what `PublishRouter::route` branches on, and zap requests don't flow through `routePublish` — they're sent to an LNURL HTTP callback per NIP-57 §3, not published through the normal pool. Kind 9734 is in the routing spec; it just enters via a different door.

Pure vocabulary-only constants — `Report` (1984), `LiveActivity` (30311), `Job` (5000-5999), etc. — never enter the routing spec at all. A future kind registry package (or `innis/nostr-core`) is the right home for those names. (`ProfileMetadata` (0) and `FollowList` (3) earned their place by being indexed kinds; the rule remains "drives a routing decision, or out.")

### Publish branches

`PublishRouter::route($event, $context)` dispatches on event kind into one of three branches. Every output also has the user's `blockedRelays` subtracted.

| Branch | Triggering kinds | Output relays |
|---|---|---|
| `Dm` | 1059 (`GiftWrap`) | Per recipient (`p` tag), the recipient's newest kind-10050 inbox relays. **Empty** if the recipient has no kind 10050 (per NIP-17 "shouldn't try"). |
| `Draft` | 30024 (`LongformDraft`), 30403 (`ClassifiedListingDraft`), 31234 (`DraftEvent`) | `privateContentRelays` if non-empty, otherwise user's outbox (from newest kind 10002, `write`/`both` markers). |
| `General` | Everything else | User's outbox, plus — for `isInboxFanout` kinds — recipient inbox fanout (capped per recipient), plus — for `isIndexed` kinds — `indexerRelays`. |

`isInboxFanout` includes: 1 (`ShortNote`), 6 (`Repost`), 7 (`Reaction`), 16 (`GenericRepost`), 24 (`PublicMessage`), 1111 (`Comment`), 9802 (`Highlight`). For these kinds, every `p`-tagged recipient's newest kind-10002 inbox (`read`/`both` markers; unmarked entries count as `both`) is unioned in. If the recipient has no usable inbox — either because kind 10002 is absent OR because the cached kind 10002 has no `read`/`both` entries (write-only) — the lib falls back to the position-`[2]` relay hint on the `p` tag, if any. Per-recipient cap defaults to 3 (`PublishContext::DEFAULT_PER_RECIPIENT_CAP`).

`isIndexed` includes: 0 (`ProfileMetadata`), 3 (`FollowList`), 10002 (`RelayList`), 10050 (`DmRelayList`). When publishing one of these, `indexerRelays` are unioned into the General output so well-known indexers (`purplepag.es`, `user.kindpag.es`, `relay.nos.social`, etc.) see the updated event. The set matches welshman's `INDEXED_KINDS`.

### Read branches

`ReadRouter::route($context)` dispatches on filter shape. Pattern detection is also exposed as a primitive via `FilterPatternClassifier::classify($filters)`, which returns `ReadBranch` directly. Every output has `blockedRelays` subtracted.

| Branch | Triggering filter shape | Output relays |
|---|---|---|
| `Search` | Any filter has a non-null `search` field | `searchRelays` ∪ `callerRelays`. |
| `DmInbox` | **Every** filter is exactly `{kinds: [1059], #p: [singleRecipient]}` and the recipient is the same across filters | Recipient's newest kind-10050 inbox relays, or `null` if none are available (see below). |
| `General` | Anything else (including no filters) | `userRelayUrls` ∪ `callerRelays`. |

### DM branches return null with no fallback

The DM branches — `ReadBranch::DmInbox` and `PublishBranch::Dm` — are the only branches whose `relays` can be `null`. Both return null when the recipient has no cached kind-10050 event, when the kind-10050 event extracts to no relays, or when blocking removes every DM relay the recipient declared. There is **no fallback** to `callerRelays`, `userRelayUrls`, or any other source — a gift-wrap publish or subscription cannot quietly redirect to relays the recipient has not authorised without leaking metadata about who the caller is talking to. A null result means "the caller cannot honour this DM; do not act."

The other branches never return null; they may return an empty array if their inputs are all empty (e.g. user has no outbox and the caller supplied no relays), which signals caller misconfiguration rather than a routing refusal:

| `relays` value | Meaning |
|---|---|
| `null` | Branch is `Dm` / `DmInbox` and no DM-relay route exists. By design. Do not act. |
| `[]` | Branch is non-DM and the inputs produced nothing. Caller misconfiguration. |
| non-empty | Use these relays. |

### Unknown kinds

Any kind not specifically named in the policy routes as `PublishBranch::General` with no recipient fanout and no indexer relays — i.e. to the user's outbox only. This is deliberate: the spec-derived default for an unrecognised kind is "publish to the author's own outbox, nothing more."

Two paths to change that:

- **Adapter layer (preferred for app-specific behaviour).** If your client has its own routing intuition for a new or experimental kind, handle it in the adapter that wraps this library. Inspect `$event->getKind()`, branch as you wish, and feed your selected relays directly into your pool. The library returns its General-branch answer; your adapter overlays your policy on top. This keeps the spec library small and stable.
- **Library (only when the rule belongs in the spec).** If a NIP defines routing for a new kind and the lib should encode that NIP-derived rule for everyone, add a case to `EventKind` and an arm to the relevant helper or branch. This adds a corpus vector and a binding decision across every port. Reserve it for behaviour that's a property of the protocol, not of your app.

### Blocklist application

`blockedRelays` is subtracted from every routing output uniformly. The list is the caller's responsibility to pre-extract from kind 10006 events (use `RelayListExtractor::blocked($event->getTags())`). The library never connects to relays, so "blocked" here means "filtered out of routing outputs"; the engine layer above the library should also refuse to open connections to these URLs.

### Search relays

`searchRelays` is the caller's pre-extracted list from their kind 10007 event. It is unioned (alongside `callerRelays`) into the `Search` branch only. It is not consulted for `General` or `DmInbox` reads.

## Architecture

```
src/Domain/
  Entity/
    Event.php                    Minimal event carrier (kind, pubkey, created_at, tags)
    Filter.php                   Minimal filter carrier (kinds, #p, search)
  Enum/
    EventKind.php                Backed enum of well-known kinds + static isInboxFanout / isDraft / isIndexed
    Route/
      PublishBranch.php          (General | Dm | Draft)
      ReadBranch.php             (Search | DmInbox | General)
  Service/                       Pure stateless services
    RelayListExtractor.php
    RelaySetBuilder.php
    EventSelector.php
    PublishRouter.php
    ReadRouter.php
    AuthorReadRouter.php
    AuthorRelaySelector.php
    ZapRequestRelaySelector.php
    RelayHintSelector.php
    FilterPatternClassifier.php
    MissingRelayListFinder.php
  ValueObject/
    Identity/PublicKey.php
    Protocol/RelayUrl.php                    (with isOnion/isLoopback/isLocalAddr/isInsecure)
    Tag.php                                  Single-tag wrapper (value-indexed string access + fromRaw factory)
    Context/                                 Typed inputs (PublishContext, ReadContext, etc.)
    Route/                                   Typed outputs (PublishRoute, ReadRoute, AuthorReadRoute, enums)
```

There is no Application layer because no infrastructure ports are needed. There is no Infrastructure layer because there are no adapters. Routing is pure protocol logic.

### Relationship to `innis/nostr-core`

`innis/nostr-relay-selection` deliberately **does not depend** on `innis/nostr-core`. The two libraries are independent and can be used together or separately.

`PublicKey`, `RelayUrl`, `Event`, `Filter` and `Tag` are re-declared here in minimal form, because re-declaring five small types is preferable to forcing every consumer of relay selection to adopt nostr-core — and a particular version of it. The reasoning, and what the duplication costs, is recorded in [ADR-0002](docs/adr/0002-the-library-re-declares-the-protocol-types-it-needs.md). If you are already using nostr-core, convert at the boundary (`PublicKey::tryFromHex($core->toHex())`).

## What this library does NOT do

Deliberate omissions. These belong in the engine layer wrapping the lib, not in the lib itself.

- **No empirical scoring.** No `received_events` counters, no time-decay, no popularity sort. The lib treats all spec-derived relays as equally valid; ordering follows the NIP rules and input order only.
- **No fetch tracking / no learning.** The lib does not know what relays you've connected to, succeeded with, or failed against. State is the caller's responsibility.
- **No caching.** Every call re-reads the events you pass in. Cache them yourself if you need to.
- **No randomness, no tie-breaks.** Two calls with the same inputs return the same outputs in the same order. Always.
- **No hardcoded fallback URLs.** If your routing returns empty, the caller decides what to do. The lib will never silently inject `wss://relay.damus.io` or any other default.
- **No pool-state awareness.** Whether a relay is currently connected is invisible to the lib.
- **No transport, no crypto, no I/O.** The lib has no `connect()`, no `publish()`, no `sign()`, no `decrypt()`. Pure data in, pure data out.

## Testing

```bash
composer test          # Unit + Compliance + PHPStan (ship gate)
composer test-unit     # Unit suite only
composer analyse       # PHPStan level 9
composer fix-style     # php-cs-fixer
composer check-style   # php-cs-fixer, dry run (CI gate)
composer check-rector  # Rector 8.4 modernisation, dry run (CI gate)
```

The compliance suite loads JSON test vectors under `tests/corpus/`. The corpus is the spec — any divergence between implementations is a test failure.

The corpus includes signed events imported verbatim from [`rust-nostr/nostr`](https://github.com/rust-nostr/nostr)'s gossip test suite under `tests/corpus/external-fixtures/rust-nostr/`. Each fixture carries a `source` field naming the upstream test it came from. The imported events are unmodified; our policy outputs are independently derived from the NIPs and may differ from rust-nostr's. Sharing fixture inputs across implementations makes any such divergence inspectable.

## Anti-patterns

- **Calling routing services directly from many places in your app.** Wrap them in a single adapter so policy lives in one place. If app-specific behaviour (defaults, fallbacks, pool state, home-relay ordering) leaks into the routing call sites, you'll end up with N divergent policy stacks instead of one.
- **Adding app-specific logic to this lib.** If your change needs to know about pool state, default relays, or the home relay, it belongs in the adapter, not here. The lib must remain pure and portable.
- **Adding a routing service without a corresponding test vector.** The corpus is the spec. Add a vector to `tests/corpus/*.json` and the harness picks it up automatically.
- **Putting a new kind into a relay set at the call site.** Add a case to `EventKind` and extend the appropriate `isInboxFanout` / `isDraft` / `isIndexed` predicate here, so every caller's routing changes consistently.

## Architecture decisions

Design rationale — the deliberate choices that read like smells until you know why, including why this library re-declares the protocol types rather than depending on `innis/nostr-core` — lives in version-controlled records under [`docs/adr/`](docs/adr/).

## License

MIT License. See LICENSE file for details.
