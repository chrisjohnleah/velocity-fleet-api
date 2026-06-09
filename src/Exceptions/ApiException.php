<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Exceptions;

use Saloon\Http\Response;
use Throwable;

/**
 * Thrown when the Velocity Fleet API returns an error response (or could not be
 * reached). Carries the HTTP status and raw body for inspection, and surfaces
 * the API's own error message where one can be extracted from the common Django
 * REST Framework / SimpleJWT / OAuth2 error shapes.
 *
 * @phpstan-consistent-constructor
 */
class ApiException extends VelocityFleetException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): static
    {
        $status = $response->status();
        $message = self::extractMessage($response)
            ?? sprintf('Velocity Fleet API request failed with HTTP %d.', $status);

        return new static($message, $status, $response->body());
    }

    /**
     * Pull a human-readable message out of the response, tolerating the error
     * shapes this Django stack returns:
     *  - DRF:       {"detail": "..."} or {"<field>": ["..."]}
     *  - SimpleJWT: {"detail": "...", "code": "...", "messages": [{"message": "..."}]}
     *  - OAuth2:    {"error": "...", "error_description": "..."}
     */
    private static function extractMessage(Response $response): ?string
    {
        // Decode defensively rather than via Response::json(): error bodies are
        // frequently non-JSON (HTML 5xx debug pages, proxy/WAF blocks), and
        // Saloon's json() decodes with JSON_THROW_ON_ERROR — which would throw a
        // \JsonException straight through the SDK's typed-exception guarantee.
        $data = json_decode((string) $response->body(), true);

        if (! is_array($data)) {
            return null;
        }

        foreach (['detail', 'error_description', 'error', 'message'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        // Fall back to any nested message strings (field errors, SimpleJWT messages).
        $strings = [];

        array_walk_recursive($data, static function (mixed $value) use (&$strings): void {
            if (is_string($value) && $value !== '') {
                $strings[] = $value;
            }
        });

        return $strings === [] ? null : implode(' ', array_slice($strings, 0, 5));
    }
}
