<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Resources;

use ChrisJohnLeah\VelocityFleet\Data\Device;
use ChrisJohnLeah\VelocityFleet\Data\DevicePositions;
use ChrisJohnLeah\VelocityFleet\Requests\DevicePositions\GetDevicePositions;
use ChrisJohnLeah\VelocityFleet\VelocityFleet;

final readonly class DevicePositionsResource
{
    public function __construct(private VelocityFleet $velocity)
    {
    }

    /**
     * The full device-positions payload for a customer (devices, groups, and
     * account-level flags).
     *
     * @param  string  $customerId  the customer's unique id (the map key from
     *                              {@see \ChrisJohnLeah\VelocityFleet\Data\Customer::$id})
     */
    public function forCustomer(string $customerId): DevicePositions
    {
        /** @var DevicePositions $positions */
        $positions = $this->velocity->send(new GetDevicePositions($customerId))->dto();

        return $positions;
    }

    /**
     * Just the flat list of devices for a customer.
     *
     * @return list<Device>
     */
    public function devices(string $customerId): array
    {
        return $this->forCustomer($customerId)->devices;
    }
}
