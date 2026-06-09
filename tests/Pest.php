<?php

declare(strict_types=1);

use ChrisJohnLeah\VelocityFleet\Auth\ArrayTokenStore;
use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;
use ChrisJohnLeah\VelocityFleet\Contracts\TokenStore;
use ChrisJohnLeah\VelocityFleet\Tests\TestCase;
use ChrisJohnLeah\VelocityFleet\VelocityFleet;
use ChrisJohnLeah\VelocityFleet\VelocityFleetConnector;
use Saloon\Config;
use Saloon\Http\Faking\MockClient;

/*
 * Bind the base test case and guarantee no test ever performs a real HTTP call —
 * any unmocked request throws instead of hitting the network.
 */
uses(TestCase::class)
    ->beforeEach(fn () => Config::preventStrayRequests())
    ->in('Feature', 'Unit');

/**
 * Build a VelocityFleet client wired to a mock client. Defaults to a static
 * access token; pass a store/connector for the refresh-token scenarios.
 */
function velocityFleet(MockClient $mock, ?TokenStore $store = null, ?VelocityFleetConnector $connector = null): VelocityFleet
{
    $connector ??= new VelocityFleetConnector();
    $connector->withMockClient($mock);

    return new VelocityFleet(
        $connector,
        $store ?? new ArrayTokenStore(new StoredToken('access-token')),
    );
}
