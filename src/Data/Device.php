<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Data;

use ChrisJohnLeah\VelocityFleet\Data\Concerns\MapsAttributes;
use DateTimeImmutable;

/**
 * A single Telematics device (vehicle) and its most recent position.
 */
final readonly class Device
{
    use MapsAttributes;

    /**
     * @param  list<int>  $deviceGroups
     * @param  list<int>  $groups
     * @param  list<int>  $driverGroups
     */
    public function __construct(
        public ?int $id = null,
        public ?string $mobilePhone = null,
        public ?bool $private = null,
        public ?float $lat = null,
        public ?float $lon = null,
        public ?string $driverName = null,
        public ?string $driverNameListDisplay = null,
        public ?string $previousDriver = null,
        public ?string $vehicleRegistration = null,
        public ?string $serviceId = null,
        public ?string $driverId = null,
        public ?string $ignition = null,
        public ?int $speed = null,
        public ?string $speedMeasureText = null,
        public ?float $direction = null,
        public ?string $street = null,
        public ?string $town = null,
        public ?string $country = null,
        public ?string $postCode = null,
        public array $deviceGroups = [],
        public array $groups = [],
        public ?string $groupColor = null,
        public array $driverGroups = [],
        public ?string $deviceGroupColor = null,
        public ?string $time = null,
        public ?string $signalStrengthColor = null,
        public ?int $timestamp = null,
        public ?string $driverGroupColor = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::integer($data, 'id'),
            mobilePhone: self::string($data, 'mobile_phone'),
            private: self::boolean($data, 'private'),
            lat: self::float($data, 'lat'),
            lon: self::float($data, 'lon'),
            driverName: self::string($data, 'driver_name'),
            driverNameListDisplay: self::string($data, 'driver_name_list_display'),
            previousDriver: self::string($data, 'previous_driver'),
            vehicleRegistration: self::string($data, 'vehicle_registration'),
            serviceId: self::string($data, 'service_id'),
            driverId: self::string($data, 'driver_id'),
            ignition: self::string($data, 'ignition'),
            speed: self::integer($data, 'speed'),
            speedMeasureText: self::string($data, 'speed_measure_text'),
            direction: self::float($data, 'direction'),
            street: self::string($data, 'street'),
            town: self::string($data, 'town'),
            country: self::string($data, 'country'),
            postCode: self::string($data, 'post_code'),
            deviceGroups: self::intList($data, 'device_groups'),
            groups: self::intList($data, 'groups'),
            groupColor: self::string($data, 'group_color'),
            driverGroups: self::intList($data, 'driver_groups'),
            deviceGroupColor: self::string($data, 'device_group_color'),
            time: self::string($data, 'time'),
            signalStrengthColor: self::string($data, 'signal_strength_color'),
            timestamp: self::integer($data, 'timestamp'),
            driverGroupColor: self::string($data, 'driver_group_color'),
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromNullable(?array $data): ?self
    {
        return $data === null ? null : self::fromArray($data);
    }

    /**
     * The ignition state as a tri-state boolean: true ("Y"), false ("N"), or
     * null when not reported.
     */
    public function ignitionOn(): ?bool
    {
        return match (strtoupper((string) $this->ignition)) {
            'Y' => true,
            'N' => false,
            default => null,
        };
    }

    /**
     * The moment this position was recorded, derived from the unix {@see $timestamp}.
     */
    public function occurredAt(): ?DateTimeImmutable
    {
        if ($this->timestamp === null) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('U', (string) $this->timestamp);

        return $time === false ? null : $time;
    }
}
