<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Exceptions;

/**
 * Thrown on a 401/403 from the API — the access token is missing, invalid, or
 * expired, or the authenticated principal lacks permission. When a refresh token
 * is available the SDK attempts a single refresh-and-retry before surfacing this.
 */
final class AuthenticationException extends ApiException
{
}
