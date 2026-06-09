# Velocity Fleet API — PHP SDK

[![CI](https://github.com/chrisjohnleah/velocity-fleet-api/actions/workflows/ci.yml/badge.svg)](https://github.com/chrisjohnleah/velocity-fleet-api/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/chrisjohnleah/velocity-fleet-api.svg)](https://packagist.org/packages/chrisjohnleah/velocity-fleet-api)
[![Total Downloads](https://img.shields.io/packagist/dt/chrisjohnleah/velocity-fleet-api.svg)](https://packagist.org/packages/chrisjohnleah/velocity-fleet-api)
[![PHP Version](https://img.shields.io/packagist/php-v/chrisjohnleah/velocity-fleet-api.svg)](https://packagist.org/packages/chrisjohnleah/velocity-fleet-api)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A modern, framework-agnostic PHP SDK for the [Radius Velocity Fleet](https://www.velocityfleet.com) Telematics API, built on [Saloon](https://docs.saloon.dev). Bearer authentication, an optional OAuth2 refresh-token flow, typed responses, and transient-error backoff — all baked in.

> **Using Laravel?** Reach for the companion bridge
> [`chrisjohnleah/velocity-fleet-api-laravel`](https://github.com/chrisjohnleah/velocity-fleet-api-laravel)
> for a service provider, config, and a persistent token store.

## What it covers

The Velocity Telematics API exposes a small, focused surface — and this SDK wraps all of it:

| Endpoint | SDK |
|---|---|
| List the customers linked to your user | `$velocity->customers()->list()` |
| List live device (vehicle) positions for a customer | `$velocity->devicePositions()->forCustomer($id)` |

## Requirements

- PHP 8.3+
- A Velocity Fleet API token (generated in the UI), **or** a customer-issued refresh token if you're a third-party integration.

## Installation

```bash
composer require chrisjohnleah/velocity-fleet-api
```

## Quick start

### With an API token (existing customers)

Generate a token in the Velocity UI under **Account → Account Settings → API Integrations → Create API Token**, then:

```php
use ChrisJohnLeah\VelocityFleet\VelocityFleet;

$velocity = VelocityFleet::withToken(getenv('VELOCITY_API_TOKEN'));

// Every customer linked to your user — typed.
foreach ($velocity->customers()->list() as $customer) {
    printf("%s (#%s) — %s\n", $customer->name, $customer->number, $customer->product);
}
```

### With a refresh token (third-party integrations)

Your customer supplies a **Refresh Token**. The SDK exchanges it for a short-lived access token on first use (standard OAuth2 `refresh_token` grant), and refreshes again whenever a call comes back unauthorised:

```php
use ChrisJohnLeah\VelocityFleet\VelocityFleet;

$velocity = VelocityFleet::withRefreshToken(
    refreshToken: getenv('VELOCITY_REFRESH_TOKEN'),
    clientId: getenv('VELOCITY_CLIENT_ID'),         // if your OAuth client requires it
    clientSecret: getenv('VELOCITY_CLIENT_SECRET'),
);
```

## Reading device positions

```php
$positions = $velocity->devicePositions()->forCustomer($customer->id);

echo "{$positions->deviceCount} devices\n";

foreach ($positions->devices as $device) {
    printf(
        "%s @ %.5f,%.5f — %d %s, ignition %s, seen %s\n",
        $device->vehicleRegistration,
        $device->lat ?? 0.0,
        $device->lon ?? 0.0,
        $device->speed ?? 0,
        $device->speedMeasureText ?? '',
        $device->ignitionOn() ? 'on' : 'off',
        $device->occurredAt()?->format('H:i') ?? 'n/a',
    );
}

// The same devices are also grouped:
foreach ($positions->deviceGroups as $group) {
    echo "{$group->name}: ".count($group->devices)." devices\n";
}
```

> **Use the right id.** The customers response is keyed by each customer's **unique id** — exposed as `Customer::$id`. Pass *that* to `forCustomer()`, not the human-facing `Customer::$number`.

## Persisting tokens

When you use the refresh-token flow, implement [`Contracts\TokenStore`](src/Contracts/TokenStore.php) to keep the rotated token between requests (the in-memory `ArrayTokenStore` only lives for the current process). The token endpoint may rotate the refresh token, so your `put()` must always overwrite the previous record:

```php
use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;
use ChrisJohnLeah\VelocityFleet\Contracts\TokenStore;
use ChrisJohnLeah\VelocityFleet\VelocityFleet;
use ChrisJohnLeah\VelocityFleet\VelocityFleetConnector;

final class MyTokenStore implements TokenStore
{
    public function get(): ?StoredToken { /* load access/refresh/expiresAt */ }
    public function put(StoredToken $token): void { /* overwrite */ }
    public function forget(): void { /* delete */ }
}

$velocity = new VelocityFleet(
    new VelocityFleetConnector(clientId: '…', clientSecret: '…'),
    new MyTokenStore(),
);
```

## Errors

Failures surface as typed exceptions, all extending `Exceptions\VelocityFleetException`:

| Exception | When |
|---|---|
| `NotConnectedException` | No token available (and none could be obtained) |
| `AuthenticationException` | `401` / `403` after a refresh attempt — re-authorise |
| `ApiException` | Any other API error or transport failure (carries `->status` and `->body`) |

```php
use ChrisJohnLeah\VelocityFleet\Exceptions\ApiException;

try {
    $velocity->devicePositions()->forCustomer($id);
} catch (ApiException $e) {
    report("Velocity API {$e->status}: {$e->getMessage()}");
}
```

## A note on authentication details

The Velocity API is a Django REST Framework service using Bearer (SimpleJWT) access tokens, with token issuance via an OAuth2 endpoint (django-oauth-toolkit). The third-party refresh-token exchange isn't part of the public reference, so the SDK targets the standard OAuth2 `refresh_token` grant at `https://www.velocityfleet.com/o/token/` by default. If your integration documents a different token endpoint or client-authentication requirement, pass it through `VelocityFleetConnector` (`tokenEndpoint`, `clientId`, `clientSecret`) — no code changes needed.

## Sending raw requests

Anything not yet wrapped in a resource can be sent through the client, which still applies auth, refresh-on-401, and typed error handling:

```php
use ChrisJohnLeah\VelocityFleet\Requests\Customers\GetCustomers;

$customers = $velocity->send(new GetCustomers())->dto();
```

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan (max)
composer lint      # Pint --test
composer check     # all three
```

Tests never hit the network — every request is faked with Saloon's `MockClient`.

## Contributing

Issues and PRs welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Please report security issues privately per [SECURITY.md](SECURITY.md).

## Licence

MIT © [Chris John Leah](https://github.com/chrisjohnleah). See [LICENSE](LICENSE).

> Not affiliated with or endorsed by Radius or Velocity Fleet. "Radius", "Velocity" and "Kinesis" are trademarks of their respective owners.
