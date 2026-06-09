<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Requests\Auth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Exchange a refresh token for a fresh access token via the OAuth2
 * `refresh_token` grant (django-oauth-toolkit token endpoint).
 *
 * Posts an `application/x-www-form-urlencoded` body to an absolute token endpoint
 * URL, so {@see $allowBaseUrlOverride} is enabled to bypass Saloon's base-URL guard.
 */
class RefreshAccessToken extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public ?bool $allowBaseUrlOverride = true;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $refreshToken,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ];

        if ($this->clientId !== null) {
            $body['client_id'] = $this->clientId;
        }

        if ($this->clientSecret !== null) {
            $body['client_secret'] = $this->clientSecret;
        }

        return $body;
    }
}
