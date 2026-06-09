<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Data;

use ChrisJohnLeah\VelocityFleet\Data\Concerns\MapsAttributes;

/**
 * A grouping of devices. The API uses the same shape for both device groups
 * (type 2) and driver groups (type 3); the {@see $type} discriminates.
 */
final readonly class DeviceGroup
{
    use MapsAttributes;

    /**
     * @param  list<Device>  $devices
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $color = null,
        public array $devices = [],
        public ?int $type = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::integer($data, 'id'),
            name: self::string($data, 'name'),
            color: self::string($data, 'color'),
            devices: array_map(
                static fn (array $device): Device => Device::fromArray($device),
                self::nestedList($data, 'devices'),
            ),
            type: self::integer($data, 'type'),
        );
    }
}
