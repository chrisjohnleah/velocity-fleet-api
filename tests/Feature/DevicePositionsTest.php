<?php

declare(strict_types=1);

use ChrisJohnLeah\VelocityFleet\Auth\ArrayTokenStore;
use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;
use ChrisJohnLeah\VelocityFleet\Data\Device;
use ChrisJohnLeah\VelocityFleet\Data\DeviceGroup;
use ChrisJohnLeah\VelocityFleet\Data\DevicePositions;
use ChrisJohnLeah\VelocityFleet\Requests\DevicePositions\GetDevicePositions;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function devicePositionsPayload(): array
{
    $device = [
        'id' => 99999,
        'mobile_phone' => null,
        'private' => false,
        'lat' => 52.4862,
        'lon' => -1.8904,
        'driver_name' => 'Joe Bloggs - Mitsubishi',
        'driver_name_list_display' => 'Joe Bloggs - Mitsubishi',
        'previous_driver' => '',
        'vehicle_registration' => 'AB12 ABC',
        'service_id' => '1234abcd-12ab-12ab-12ab-123456abcdef',
        'driver_id' => '',
        'ignition' => 'Y',
        'speed' => 0,
        'speed_measure_text' => 'MPH',
        'direction' => 317.0,
        'street' => 'A999',
        'town' => null,
        'country' => 'United Kingdom',
        'post_code' => 'XX1 1XX',
        'device_groups' => [99999],
        'groups' => [99999],
        'group_color' => '#EB9393',
        'driver_groups' => [],
        'device_group_color' => '#EB9393',
        'time' => '14:16 </br> 13/09/2024',
        'signal_strength_color' => 'green',
        'timestamp' => '1726233388',
        'driver_group_color' => '#288ecd',
    ];

    return [
        'show_marker_reg_text' => true,
        'driver_groups' => [
            ['id' => 1, 'name' => 'Drivers', 'color' => '#009DD1', 'devices' => [], 'type' => 3],
        ],
        'device_groups' => [
            ['id' => 99999, 'name' => 'Fleet', 'color' => '#EB9393', 'devices' => [$device], 'type' => 2],
        ],
        'devices' => [$device],
        'iso_numbers' => [789672, 706405],
        'device_count' => 20,
        'kinesis_core_customer' => true,
        'eroute_url' => 'http://www.erouteonline.com',
        'KINESIS_LIVE_MAP_REFRESH_RATE' => 30000,
        'KINESIS_LIVE_MAP_LARGE_FLEET_REFRESH_RATE' => 90000,
    ];
}

it('hydrates the device-positions envelope', function () {
    $mock = new MockClient([
        GetDevicePositions::class => MockResponse::make(devicePositionsPayload()),
    ]);

    $positions = velocityFleet($mock, new ArrayTokenStore(new StoredToken('t')))
        ->devicePositions()
        ->forCustomer('11111');

    expect($positions)->toBeInstanceOf(DevicePositions::class)
        ->and($positions->showMarkerRegText)->toBeTrue()
        ->and($positions->deviceCount)->toBe(20)
        ->and($positions->kinesisCoreCustomer)->toBeTrue()
        ->and($positions->erouteUrl)->toBe('http://www.erouteonline.com')
        ->and($positions->liveMapRefreshRate)->toBe(30000)
        ->and($positions->liveMapLargeFleetRefreshRate)->toBe(90000)
        ->and($positions->isoNumbers)->toBe([789672, 706405])
        ->and($positions->devices)->toHaveCount(1)
        ->and($positions->deviceGroups)->toHaveCount(1)
        ->and($positions->driverGroups)->toHaveCount(1);
});

it('hydrates a Device with typed position fields and helpers', function () {
    $mock = new MockClient([
        GetDevicePositions::class => MockResponse::make(devicePositionsPayload()),
    ]);

    $device = velocityFleet($mock, new ArrayTokenStore(new StoredToken('t')))
        ->devicePositions()
        ->forCustomer('11111')
        ->devices[0];

    expect($device)->toBeInstanceOf(Device::class)
        ->and($device->id)->toBe(99999)
        ->and($device->lat)->toBe(52.4862)
        ->and($device->lon)->toBe(-1.8904)
        ->and($device->vehicleRegistration)->toBe('AB12 ABC')
        ->and($device->ignition)->toBe('Y')
        ->and($device->ignitionOn())->toBeTrue()
        ->and($device->speed)->toBe(0)
        ->and($device->speedMeasureText)->toBe('MPH')
        ->and($device->direction)->toBe(317.0)
        ->and($device->deviceGroups)->toBe([99999])
        ->and($device->groups)->toBe([99999])
        ->and($device->driverGroups)->toBe([])
        ->and($device->timestamp)->toBe(1726233388)
        ->and($device->occurredAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($device->occurredAt()->getTimestamp())->toBe(1726233388);
});

it('nests devices inside device groups and keeps driver groups distinct', function () {
    $mock = new MockClient([
        GetDevicePositions::class => MockResponse::make(devicePositionsPayload()),
    ]);

    $positions = velocityFleet($mock, new ArrayTokenStore(new StoredToken('t')))
        ->devicePositions()
        ->forCustomer('11111');

    $deviceGroup = $positions->deviceGroups[0];
    $driverGroup = $positions->driverGroups[0];

    expect($deviceGroup)->toBeInstanceOf(DeviceGroup::class)
        ->and($deviceGroup->id)->toBe(99999)
        ->and($deviceGroup->type)->toBe(2)
        ->and($deviceGroup->devices)->toHaveCount(1)
        ->and($deviceGroup->devices[0]->vehicleRegistration)->toBe('AB12 ABC')
        ->and($driverGroup->type)->toBe(3)
        ->and($driverGroup->devices)->toBe([]);
});

it('sends the customer as a query parameter', function () {
    $mock = new MockClient([
        GetDevicePositions::class => MockResponse::make(devicePositionsPayload()),
    ]);

    velocityFleet($mock, new ArrayTokenStore(new StoredToken('t')))
        ->devicePositions()
        ->forCustomer('22222');

    $mock->assertSent(fn ($request): bool => $request->query()->get('customer') === '22222');
});

it('exposes the device-positions endpoint with a trailing slash', function () {
    expect((new GetDevicePositions('1'))->resolveEndpoint())
        ->toBe('/api/mobile/kinesis/device-live-positions/');
});
