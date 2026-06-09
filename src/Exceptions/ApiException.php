<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Exceptions;

use DateTimeImmutable;
use Saloon\Http\Response;
use Throwable;

/**
 * Thrown when the Velocity Fleet API returns an error response (or could not be
 * reached). Carries the HTTP status, response headers, and raw body for
 * inspection, and surfaces the API's own error message where one can be
 * extracted from the common Django REST Framework / SimpleJWT / OAuth2 error
 * shapes.
 *
 * @phpstan-consistent-constructor
 */
class ApiException extends VelocityFleetException
{
    /**
     * @param  array<string, mixed>  $headers  Response headers, keyed by name.
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $body = null,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): static
    {
        $status = $response->status();
        $message = self::extractMessage($response)
            ?? sprintf('Velocity Fleet API request failed with HTTP %d.', $status);

        return new static($message, $status, $response->body(), $response->headers()->all());
    }

    /**
     * Case-insensitive lookup of a single response header value (the first value
     * when a header is multi-valued). Returns null when the header is absent.
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                $value = is_array($value) ? ($value[0] ?? null) : $value;

                return is_scalar($value) ? (string) $value : null;
            }
        }

        return null;
    }

    /**
     * The number of seconds the server is asking the client to wait before
     * retrying, parsed from the `Retry-After` response header (RFC 9110). Handles
     * both the delay-seconds form (`Retry-After: 120`) and the HTTP-date form
     * (`Retry-After: Wed, 09 Jun 2026 12:02:00 GMT`). Returns null when the header
     * is absent or unparseable; never returns a negative value.
     */
    public function retryAfter(?DateTimeImmutable $now = null): ?int
    {
        $value = $this->header('Retry-After');

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - ($now ?? new DateTimeImmutable())->getTimestamp());
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
