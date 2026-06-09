<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Requests\Customers;

use ChrisJohnLeah\VelocityFleet\Data\Customer;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * List the customers linked to the authenticated user.
 *
 * GET /vapi/v1/accounts/users/customers/
 *
 * The response is a JSON object keyed by each customer's unique id; this request
 * flattens it into a list of {@see Customer}, injecting the key as the id.
 */
class GetCustomers extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/vapi/v1/accounts/users/customers/';
    }

    /**
     * @return list<Customer>
     */
    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        $customers = [];

        foreach ($data as $id => $info) {
            if (! is_array($info)) {
                continue;
            }

            /** @var array<string, mixed> $info */
            $info['id'] = (string) $id;
            $customers[] = Customer::fromArray($info);
        }

        return $customers;
    }
}
