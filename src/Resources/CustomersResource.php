<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Resources;

use ChrisJohnLeah\VelocityFleet\Data\Customer;
use ChrisJohnLeah\VelocityFleet\Requests\Customers\GetCustomers;
use ChrisJohnLeah\VelocityFleet\VelocityFleet;

final readonly class CustomersResource
{
    public function __construct(private VelocityFleet $velocity)
    {
    }

    /**
     * Every customer linked to the authenticated user.
     *
     * @return list<Customer>
     */
    public function list(): array
    {
        /** @var list<Customer> $customers */
        $customers = $this->velocity->send(new GetCustomers())->dto();

        return $customers;
    }
}
