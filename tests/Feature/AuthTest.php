<?php

declare(strict_types=1);

use ChrisJohnLeah\VelocityFleet\Auth\ArrayTokenStore;
use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;
use ChrisJohnLeah\VelocityFleet\Exceptions\ApiException;
use ChrisJohnLeah\VelocityFleet\Exceptions\AuthenticationException;
use ChrisJohnLeah\VelocityFleet\Exceptions\NotConnectedException;
use ChrisJohnLeah\VelocityFleet\Requests\Auth\RefreshAccessToken;
use ChrisJohnLeah\VelocityFleet\Requests\Customers\GetCustomers;
use ChrisJohnLeah\VelocityFleet\VelocityFleet;
use ChrisJohnLeah\VelocityFleet\VelocityFleetConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('throws when no token has been stored', function () {
    $velocity = new VelocityFleet(new VelocityFleetConnector(), new ArrayTokenStore());

    $velocity->connector();
})->throws(NotConnectedException::class);

it('applies the access token as a Bearer header', function () {
    $mock = new MockClient([
        GetCustomers::class => MockResponse::make([]),
    ]);

    velocityFleet($mock, new ArrayTokenStore(new StoredToken('my-token')))
        ->customers()
        ->list();

    expect($mock->getLastPendingRequest()?->headers()->get('Authorization'))->toBe('Bearer my-token');
});

it('exchanges a refresh-token seed on first use and persists rotated tokens', function () {
    $store = new ArrayTokenStore(new StoredToken(
        accessToken: '',
        refreshToken: 'old-refresh',
        expiresAt: new DateTimeImmutable('-1 second'),
    ));

    $mock = new MockClient([
        RefreshAccessToken::class => MockResponse::make([
            'access_token' => 'new-access',
            'refresh_token' => 'rotated-refresh',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        GetCustomers::class => MockResponse::make([]),
    ]);

    $connector = new VelocityFleetConnector(clientId: 'client-id', clientSecret: 'client-secret');

    velocityFleet($mock, $store, $connector)->customers()->list();

    expect($store->get()->accessToken)->toBe('new-access')
        ->and($store->get()->refreshToken)->toBe('rotated-refresh')
        ->and($store->get()->expiresAt)->toBeInstanceOf(DateTimeImmutable::class);

    // The exchange used the OAuth2 refresh_token grant with client credentials.
    $mock->assertSent(function ($request): bool {
        if (! method_exists($request, 'body')) {
            return false;
        }

        $body = $request->body()->all();

        return ($body['grant_type'] ?? null) === 'refresh_token'
            && ($body['refresh_token'] ?? null) === 'old-refresh'
            && ($body['client_id'] ?? null) === 'client-id';
    });

    // The follow-up data request carried the freshly minted access token.
    expect($mock->getLastPendingRequest()?->headers()->get('Authorization'))->toBe('Bearer new-access');
});

it('reactively refreshes on a 401 and retries the request once', function () {
    $store = new ArrayTokenStore(new StoredToken(
        accessToken: 'stale-access',
        refreshToken: 'good-refresh',
    ));

    // Sequence: data call 401 -> token refresh -> data call 200.
    $mock = new MockClient([
        MockResponse::make(['detail' => 'Given token not valid', 'code' => 'token_not_valid'], 401),
        MockResponse::make(['access_token' => 'fresh-access', 'expires_in' => 3600], 200),
        MockResponse::make([], 200),
    ]);

    $customers = velocityFleet($mock, $store)->customers()->list();

    expect($customers)->toBe([])
        ->and($store->get()->accessToken)->toBe('fresh-access')
        // No refresh_token in the response, so the original is preserved.
        ->and($store->get()->refreshToken)->toBe('good-refresh');

    $mock->assertSentCount(3);
});

it('surfaces a 401 with no refresh token as an AuthenticationException', function () {
    $mock = new MockClient([
        GetCustomers::class => MockResponse::make(
            ['detail' => 'Authentication credentials were not provided.'],
            401,
        ),
    ]);

    velocityFleet($mock, new ArrayTokenStore(new StoredToken('bad-token')))
        ->customers()
        ->list();
})->throws(AuthenticationException::class, 'Authentication credentials were not provided.');

it('surfaces a server error as an ApiException carrying the status', function () {
    $connector = new VelocityFleetConnector();
    $connector->tries = null; // skip retry backoff for a fast, deterministic test

    $mock = new MockClient([
        GetCustomers::class => MockResponse::make(['detail' => 'Server error'], 500),
    ]);

    try {
        velocityFleet($mock, null, $connector)->customers()->list();
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(500)
            ->and($exception->getMessage())->toBe('Server error');
    }
});

it('surfaces a non-JSON error body as an ApiException, not a JsonException', function () {
    $connector = new VelocityFleetConnector();
    $connector->tries = null; // single attempt, no backoff

    // An upstream proxy / WAF / Django debug page — HTML, not JSON.
    $mock = new MockClient([
        GetCustomers::class => MockResponse::make('<html><body>502 Bad Gateway</body></html>', 502),
    ]);

    try {
        velocityFleet($mock, null, $connector)->customers()->list();
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(502);
    }
});

it('exposes response headers and parses Retry-After on a throttled (429) response', function () {
    $connector = new VelocityFleetConnector();
    $connector->tries = null; // single attempt, no backoff

    $mock = new MockClient([
        GetCustomers::class => MockResponse::make(
            ['detail' => 'Request was throttled. Expected available in 120 seconds.'],
            429,
            ['Retry-After' => '120'],
        ),
    ]);

    try {
        velocityFleet($mock, null, $connector)->customers()->list();
        $this->fail('Expected an ApiException.');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(429)
            ->and($exception->retryAfter())->toBe(120)
            ->and($exception->header('Retry-After'))->toBe('120');
    }
});

it('refreshes at most once per 401 even when the new token is short-lived', function () {
    $store = new ArrayTokenStore(new StoredToken('stale-access', 'good-refresh'));

    // The refreshed token expires in 30s — inside the 60s proactive buffer.
    $mock = new MockClient([
        MockResponse::make(['detail' => 'token_not_valid'], 401),
        MockResponse::make(['access_token' => 'fresh-access', 'expires_in' => 30], 200),
        MockResponse::make([], 200),
    ]);

    velocityFleet($mock, $store)->customers()->list();

    // Three calls: data(401) -> refresh -> data(200). NOT four (no redundant
    // second refresh from the proactive check on the retry).
    $mock->assertSentCount(3);
});

it('rejects an empty access token', function () {
    VelocityFleet::withToken('   ');
})->throws(InvalidArgumentException::class);

it('rejects an empty refresh token', function () {
    VelocityFleet::withRefreshToken('');
})->throws(InvalidArgumentException::class);
