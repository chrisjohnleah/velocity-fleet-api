<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet;

use ChrisJohnLeah\VelocityFleet\Auth\ArrayTokenStore;
use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;
use ChrisJohnLeah\VelocityFleet\Contracts\TokenStore;
use ChrisJohnLeah\VelocityFleet\Exceptions\ApiException;
use ChrisJohnLeah\VelocityFleet\Exceptions\AuthenticationException;
use ChrisJohnLeah\VelocityFleet\Exceptions\NotConnectedException;
use ChrisJohnLeah\VelocityFleet\Exceptions\VelocityFleetException;
use ChrisJohnLeah\VelocityFleet\Requests\Auth\RefreshAccessToken;
use ChrisJohnLeah\VelocityFleet\Resources\CustomersResource;
use ChrisJohnLeah\VelocityFleet\Resources\DevicePositionsResource;
use DateTimeImmutable;
use InvalidArgumentException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * High-level entry point for the Velocity Fleet API.
 *
 * Ties a {@see VelocityFleetConnector} to a {@see TokenStore}, applies the Bearer
 * access token to every request, and — for the third-party refresh-token flow —
 * keeps the access token fresh: proactively before a known expiry, and reactively
 * on a 401 (the token lifetime is undocumented). Exposes typed resources.
 */
final class VelocityFleet
{
    public function __construct(
        private readonly VelocityFleetConnector $connector,
        private readonly TokenStore $tokenStore,
        private readonly int $refreshBufferSeconds = 60,
    ) {
    }

    /**
     * Authenticate with a ready-to-use access token — the API token an existing
     * customer generates in the UI (Account Settings > API Integrations), or one
     * you have already obtained. No refresh is performed for this connection.
     */
    public static function withToken(string $accessToken, string $baseUrl = VelocityFleetConnector::DEFAULT_BASE_URL): self
    {
        $accessToken = trim($accessToken);

        if ($accessToken === '') {
            throw new InvalidArgumentException('A non-empty access token is required.');
        }

        return new self(
            new VelocityFleetConnector($baseUrl),
            new ArrayTokenStore(new StoredToken($accessToken)),
        );
    }

    /**
     * Authenticate as a third-party integration using the Refresh Token a customer
     * supplied. The SDK exchanges it for an access token on first use (standard
     * OAuth2 `refresh_token` grant against the token endpoint) and refreshes as
     * needed. Provide client credentials if your registered OAuth client requires
     * them.
     */
    public static function withRefreshToken(
        string $refreshToken,
        ?string $clientId = null,
        ?string $clientSecret = null,
        string $baseUrl = VelocityFleetConnector::DEFAULT_BASE_URL,
        string $tokenEndpoint = VelocityFleetConnector::DEFAULT_TOKEN_ENDPOINT,
    ): self {
        $refreshToken = trim($refreshToken);

        if ($refreshToken === '') {
            throw new InvalidArgumentException('A non-empty refresh token is required.');
        }

        $connector = new VelocityFleetConnector($baseUrl, $tokenEndpoint, $clientId, $clientSecret);

        // Seed an already-expired token so the first call performs the exchange.
        $seed = new StoredToken(
            accessToken: '',
            refreshToken: $refreshToken,
            expiresAt: new DateTimeImmutable('-1 second'),
        );

        return new self($connector, new ArrayTokenStore($seed));
    }

    /**
     * The connector with a current access token applied. Refreshes proactively
     * when a known expiry is within the buffer.
     *
     * @throws NotConnectedException when no token is available
     */
    public function connector(): VelocityFleetConnector
    {
        $token = $this->tokenStore->get();

        if ($token === null) {
            throw new NotConnectedException(
                'No Velocity Fleet token stored — create the client with VelocityFleet::withToken() or '
                .'::withRefreshToken(), or seed your TokenStore first.',
            );
        }

        if ($token->refreshToken !== null && $token->expiresWithin($this->refreshBufferSeconds)) {
            $token = $this->refresh($token);
        }

        $this->connector->authenticate(new TokenAuthenticator($token->accessToken));

        return $this->connector;
    }

    /**
     * Send a request through the authenticated connector, returning the raw
     * Saloon response. On a 401 with a refresh token available, refreshes once
     * and retries before surfacing the failure as a typed exception.
     *
     * @throws VelocityFleetException on any API error or transport failure
     */
    public function send(Request $request): Response
    {
        $response = $this->dispatch($this->connector(), $request);

        if ($response->status() === 401 && $this->canRefresh()) {
            $token = $this->tokenStore->get();

            if ($token !== null && $token->refreshToken !== null) {
                // Apply the just-minted token directly rather than re-entering
                // connector(), so a single 401 triggers exactly one refresh even
                // when the new token's lifetime is shorter than the buffer.
                $refreshed = $this->refresh($token);
                $this->connector->authenticate(new TokenAuthenticator($refreshed->accessToken));
                $response = $this->dispatch($this->connector, $request);
            }
        }

        if ($response->failed()) {
            throw self::exceptionForResponse($response);
        }

        return $response;
    }

    /**
     * Exchange the stored refresh token for a fresh access token and persist the
     * result (overwriting the previous token in case the endpoint rotated it).
     *
     * @throws NotConnectedException when no refresh token / token endpoint exists
     * @throws VelocityFleetException when the exchange fails
     */
    public function refresh(StoredToken $token): StoredToken
    {
        if ($token->refreshToken === null) {
            throw new NotConnectedException(
                'No refresh token available — provide the customer-issued refresh token to obtain an access token.',
            );
        }

        $endpoint = $this->connector->getTokenEndpoint();

        if ($endpoint === null) {
            throw new NotConnectedException(
                'No OAuth token endpoint configured — set the token endpoint on VelocityFleetConnector to enable refresh.',
            );
        }

        $response = $this->dispatch($this->connector, new RefreshAccessToken(
            endpoint: $endpoint,
            refreshToken: $token->refreshToken,
            clientId: $this->connector->getClientId(),
            clientSecret: $this->connector->getClientSecret(),
        ));

        if ($response->failed()) {
            throw self::exceptionForResponse($response);
        }

        // Decode defensively — a proxy could return a 200 with a non-JSON body,
        // which Saloon's json() would throw on (JSON_THROW_ON_ERROR).
        $data = json_decode((string) $response->body(), true);

        if (! is_array($data)) {
            throw new ApiException('The token endpoint returned an unexpected response.', $response->status(), $response->body());
        }

        $accessToken = $data['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new ApiException('The token endpoint did not return an access_token.', $response->status(), $response->body());
        }

        $refreshToken = $data['refresh_token'] ?? null;

        $rotated = new StoredToken(
            accessToken: $accessToken,
            refreshToken: is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : $token->refreshToken,
            expiresAt: self::expiresAtFrom($data),
        );

        $this->tokenStore->put($rotated);

        return $rotated;
    }

    /**
     * Customers linked to the authenticated user.
     */
    public function customers(): CustomersResource
    {
        return new CustomersResource($this);
    }

    /**
     * Live device (vehicle) positions for a customer.
     */
    public function devicePositions(): DevicePositionsResource
    {
        return new DevicePositionsResource($this);
    }

    private function canRefresh(): bool
    {
        $token = $this->tokenStore->get();

        return $token !== null
            && $token->refreshToken !== null
            && $this->connector->getTokenEndpoint() !== null;
    }

    /**
     * Send through the connector without the refresh wrapper, normalising
     * transport failures and never throwing on a failed HTTP status (the caller
     * inspects the response).
     */
    private function dispatch(VelocityFleetConnector $connector, Request $request): Response
    {
        try {
            return $connector->send($request);
        } catch (RequestException $exception) {
            return $exception->getResponse();
        } catch (FatalRequestException $exception) {
            throw new ApiException(
                'Could not reach the Velocity Fleet API: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private static function exceptionForResponse(Response $response): VelocityFleetException
    {
        return in_array($response->status(), [401, 403], true)
            ? AuthenticationException::fromResponse($response)
            : ApiException::fromResponse($response);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function expiresAtFrom(array $data): ?DateTimeImmutable
    {
        $expiresIn = $data['expires_in'] ?? null;

        if (is_int($expiresIn) || (is_string($expiresIn) && is_numeric($expiresIn))) {
            return (new DateTimeImmutable())->modify(sprintf('+%d seconds', (int) $expiresIn));
        }

        return null;
    }
}
