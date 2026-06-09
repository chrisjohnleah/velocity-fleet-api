<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Request;

/**
 * Saloon connector for the Radius Velocity Fleet API.
 *
 * The API is a Django REST Framework service authenticated with Bearer access
 * tokens (SimpleJWT). Tokens are issued/refreshed via an OAuth2 token endpoint
 * (django-oauth-toolkit). This connector only carries configuration and the
 * retry policy — the {@see VelocityFleet} client owns authentication and the
 * refresh-token exchange.
 *
 * Note: API paths MUST keep their trailing slash; Django's APPEND_SLASH issues a
 * 301 redirect otherwise.
 */
class VelocityFleetConnector extends Connector
{
    public const DEFAULT_BASE_URL = 'https://www.velocityfleet.com';

    /**
     * OAuth2 token endpoint used to exchange a refresh token for an access token.
     * Derived from probing the live django-oauth-toolkit deployment; override it
     * if your integration documents a different URL.
     */
    public const DEFAULT_TOKEN_ENDPOINT = 'https://www.velocityfleet.com/o/token/';

    public ?int $tries = 3;

    public ?int $retryInterval = 1000;

    public ?bool $useExponentialBackoff = true;

    // Let the VelocityFleet client convert a final failed response into a typed
    // exception rather than throwing Saloon's generic one mid-retry.
    public ?bool $throwOnMaxTries = false;

    public function __construct(
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly ?string $tokenEndpoint = self::DEFAULT_TOKEN_ENDPOINT,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
    ) {
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * Bounded HTTP timeouts so a stalled connection can't hang a request (or a
     * poller job / cache lock) indefinitely. Guzzle defaults to no timeout.
     *
     * @return array<string, int>
     */
    protected function defaultConfig(): array
    {
        return [
            'connect_timeout' => 10,
            'timeout' => 30,
        ];
    }

    public function getTokenEndpoint(): ?string
    {
        return $this->tokenEndpoint;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * Retry transient failures: connection errors, 429s, and 5xx. Reads (the
     * device-positions call is a POST but is side-effect free) are safe to retry.
     */
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        return $status === 429 || $status >= 500;
    }
}
