<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Requests\DevicePositions;

use ChrisJohnLeah\VelocityFleet\Data\DevicePositions;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * List all live device positions for a customer.
 *
 * POST /api/mobile/kinesis/device-live-positions/?customer=:customer_id
 *
 * The customer is the unique id from {@see \ChrisJohnLeah\VelocityFleet\Data\Customer::$id}
 * (the map key of the customers response), not the customer's account number.
 */
class GetDevicePositions extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $customerId)
    {
    }

    public function resolveEndpoint(): string
    {
        return '/api/mobile/kinesis/device-live-positions/';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['customer' => $this->customerId];
    }

    public function createDtoFromResponse(Response $response): DevicePositions
    {
        $data = $response->json();

        /** @var array<string, mixed> $payload */
        $payload = is_array($data) ? $data : [];

        return DevicePositions::fromArray($payload);
    }
}
