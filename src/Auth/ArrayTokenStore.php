<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Auth;

use ChrisJohnLeah\VelocityFleet\Contracts\TokenStore;

/**
 * In-memory token store. Useful for tests, scripts, and single-process apps.
 * For persistence across requests, implement {@see TokenStore} yourself (a
 * Laravel bridge can provide an Eloquent-backed store).
 */
final class ArrayTokenStore implements TokenStore
{
    public function __construct(private ?StoredToken $token = null)
    {
    }

    public function get(): ?StoredToken
    {
        return $this->token;
    }

    public function put(StoredToken $token): void
    {
        $this->token = $token;
    }

    public function forget(): void
    {
        $this->token = null;
    }
}
