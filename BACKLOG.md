# Core SDK — improvement backlog (handoff)

> **Handoff note.** This backlog was produced on **2026-06-09** by the agent building the
> **Laravel bridge** (`chrisjohnleah/velocity-fleet-api-laravel`, the sibling repo at
> `../velocity-fleet-api-laravel`), which is the downstream consumer of this SDK. It comes
> out of a full inspection of this SDK's public surface plus an expansion analysis for the
> bridge. **Nothing here is implemented** — it's yours (the core SDK owner) to prioritise and
> action. Left uncommitted on purpose so it doesn't collide with your in-flight work; commit,
> action, or delete as you see fit.
>
> **Already shipped (thank you):** `v0.1.1` added `ApiException::$headers` + `header()` +
> `retryAfter()`. The bridge depends on this for rate-limit-aware backoff. ✅

---

## Tier 1 — natural follow-ons (high value)

### 1. Make the connector *honour* `Retry-After` in its retry loop  → suggested **v0.1.2**  · S–M
- **What:** `v0.1.1` exposed `retryAfter()` on the *exception* so callers can back off. The next
  step is the connector's own retry loop **waiting** the server-requested delay on a `429`/`503`
  instead of fixed exponential backoff. Saloon v4 lets you customise the retry delay
  (e.g. an `RateLimited`/retry-interval hook reading `Retry-After` off the response).
- **Why:** Makes the SDK self-correcting on throttling with zero caller effort — the real payoff
  of v0.1.1. The biggest reliability win available right now.
- **Notes:** Keep `throwOnMaxTries = false` semantics; only the *interval* changes. TDD against a
  `MockResponse` 429 with a `Retry-After` header and assert the computed delay.

### 2. Add `saloonphp/rate-limit-plugin` (proactive throttling)  · S
- **What:** Adopt the rate-limit plugin (the Sage sibling already uses it: `Limit::allow(...)`).
- **Why:** Fleets poll device-positions every ~30s; proactive limiting prevents hitting `429`s in
  the first place rather than only reacting to them.

## Tier 2 — forward-compatibility & DX

### 3. Expose the raw payload on DTOs (`->raw` / `toArray()`)  · S
- **What:** Give `Customer`/`Device`/`DeviceGroup`/`DevicePositions` access to their original
  decoded array (a `public readonly array $raw` captured in `fromArray()`, or a `toArray()`).
- **Why:** The telematics API can add fields faster than the SDK maps them. Letting consumers read
  unmapped fields is cheap insurance against the SDK lagging the live API.

### 4. A few pure `Device` value helpers (belong in core, not the bridge)  · S
- `hasFix(): bool` (lat **and** lon present), `coordinates(): ?array{lat,lon}`,
  `speedKmh()` / `speedMph()` normalised off `speedMeasureText`.
- **Why:** Pure value-object logic; every consumer reimplements these otherwise.

## Tier 3 — robustness / quality

### 5. Concurrency-safe refresh  · S (docs) / M (hook)
- **What:** Refresh-token **rotation** under two simultaneous processes can invalidate the chain
  (process A rotates; process B then refreshes with the now-dead token). Document that persistent
  `TokenStore` implementations should **lock around refresh**, and/or add an optional locking hook.
- **Why:** The bridge will guard this with a DB lock, but any consumer is exposed. Worth a note in
  the `TokenStore` contract docblock at minimum.

### 6. PHP 8.5 deprecation sweep + add 8.5 to CI  · S
- **What:** Sweep `src` for 8.5 deprecations and add a PHP 8.5 leg to the CI matrix.
- **Why:** Already tripped one in test code (`DATE_RFC7231` deprecated in 8.5) while writing the
  `retryAfter()` tests — worth getting ahead of it in `src`.

## Tier 4 — ops

### 7. Publish to Packagist  · S (ops)
- **What:** Publish this SDK (and the bridge) to Packagist.
- **Why:** Once this is on Packagist, the bridge drops its local `path` repository and just
  `require`s `chrisjohnleah/velocity-fleet-api: ^0.1` normally. Until then the bridge depends on
  the local checkout.

---

## Coordination notes for the bridge

- The bridge wraps this SDK's **exact public surface** (`VelocityFleet`, `VelocityFleetConnector`,
  `Contracts\TokenStore`, `Auth\StoredToken`, `Data\*`, `Resources\*`, `Exceptions\*`). Please keep
  these signatures stable, or flag breaking changes in the CHANGELOG + a minor/major bump so the
  bridge's constraint can track it. Additive changes (like v0.1.1) are ideal.
- The bridge will **consume `ApiException::retryAfter()`** for its adaptive-throttle feature, and
  relies on **`put()`-overwrites-on-rotation** in `TokenStore` — both already in place. 👍
- `StoredToken` has exactly three fields (`accessToken`, `refreshToken`, `expiresAt`) — the bridge
  mirrors that in its Eloquent store. If you ever add a field, ping the bridge.
