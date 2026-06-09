<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Data;

use ChrisJohnLeah\VelocityFleet\Data\Concerns\MapsAttributes;

/**
 * The device-positions payload for a customer: the flat list of {@see $devices},
 * the same devices organised into {@see $deviceGroups} and {@see $driverGroups},
 * and assorted account-level flags and live-map refresh hints.
 */
final readonly class DevicePositions
{
    use MapsAttributes;

    /**
     * @param  list<DeviceGroup>  $driverGroups
     * @param  list<DeviceGroup>  $deviceGroups
     * @param  list<Device>  $devices
     * @param  list<int>  $isoNumbers
     */
    public function __construct(
        public ?bool $showMarkerRegText = null,
        public array $driverGroups = [],
        public array $deviceGroups = [],
        public array $devices = [],
        public array $isoNumbers = [],
        public ?int $deviceCount = null,
        public ?bool $kinesisCoreCustomer = null,
        public ?string $erouteUrl = null,
        public ?int $liveMapRefreshRate = null,
        public ?int $liveMapLargeFleetRefreshRate = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            showMarkerRegText: self::boolean($data, 'show_marker_reg_text'),
            driverGroups: array_map(
                static fn (array $group): DeviceGroup => DeviceGroup::fromArray($group),
                self::nestedList($data, 'driver_groups'),
            ),
            deviceGroups: array_map(
                static fn (array $group): DeviceGroup => DeviceGroup::fromArray($group),
                self::nestedList($data, 'device_groups'),
            ),
            devices: array_map(
                static fn (array $device): Device => Device::fromArray($device),
                self::nestedList($data, 'devices'),
            ),
            isoNumbers: self::intList($data, 'iso_numbers'),
            deviceCount: self::integer($data, 'device_count'),
            kinesisCoreCustomer: self::boolean($data, 'kinesis_core_customer'),
            erouteUrl: self::string($data, 'eroute_url'),
            liveMapRefreshRate: self::integer($data, 'KINESIS_LIVE_MAP_REFRESH_RATE'),
            liveMapLargeFleetRefreshRate: self::integer($data, 'KINESIS_LIVE_MAP_LARGE_FLEET_REFRESH_RATE'),
        );
    }
}
