<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Auth;

use DateTimeImmutable;

/**
 * The persisted auth state for a single Velocity Fleet connection.
 *
 * Holds the Bearer access token plus — for the third-party refresh-token flow —
 * the refresh token and the access token's expiry. A connection that only ever
 * uses a UI-generated API token will have a null refresh token and null expiry.
 */
final readonly class StoredToken
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
    }

    public function hasExpired(?DateTimeImmutable $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    /**
     * True when the access token expires within the given number of seconds —
     * used to refresh proactively, just before the token lapses.
     */
    public function expiresWithin(int $seconds, ?DateTimeImmutable $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $now ??= new DateTimeImmutable();

        return $this->expiresAt <= $now->modify(sprintf('+%d seconds', $seconds));
    }
}
