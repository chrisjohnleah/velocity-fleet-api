<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Exceptions;

use RuntimeException;

/**
 * Base type for every exception thrown by the SDK. Catch this to handle any
 * Velocity Fleet failure in one place.
 */
abstract class VelocityFleetException extends RuntimeException
{
}
