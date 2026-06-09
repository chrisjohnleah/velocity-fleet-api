<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Exceptions;

/**
 * Thrown when an API call is attempted without usable credentials — no token has
 * been provided, or a refresh was needed but no refresh token / token endpoint
 * was configured to obtain one.
 */
final class NotConnectedException extends VelocityFleetException
{
}
