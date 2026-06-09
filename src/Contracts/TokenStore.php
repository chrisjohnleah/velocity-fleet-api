<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Contracts;

use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;

/**
 * Persistence boundary for a Velocity Fleet connection's token.
 *
 * The core ships an in-memory implementation; a Laravel bridge can provide an
 * Eloquent-backed store. The OAuth2 token endpoint may rotate the refresh token
 * on exchange, so implementations MUST overwrite the previous token on put().
 */
interface TokenStore
{
    public function get(): ?StoredToken;

    public function put(StoredToken $token): void;

    public function forget(): void;
}
