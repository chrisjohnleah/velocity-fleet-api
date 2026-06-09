<?php

declare(strict_types=1);

use ChrisJohnLeah\VelocityFleet\Auth\ArrayTokenStore;
use ChrisJohnLeah\VelocityFleet\Auth\StoredToken;
use ChrisJohnLeah\VelocityFleet\Data\Customer;
use ChrisJohnLeah\VelocityFleet\Requests\Customers\GetCustomers;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('hydrates customers and uses the map key as the id', function () {
    $mock = new MockClient([
        GetCustomers::class => MockResponse::make([
            '11111' => ['name' => 'Customer 1', 'number' => '111', 'product' => 'Telematics'],
            '22222' => ['name' => 'Customer 2', 'number' => '222', 'product' => 'Fuel & Telematics'],
            '33333' => ['name' => 'Customer 3', 'number' => '333', 'product' => 'Fuel'],
        ]),
    ]);

    $customers = velocityFleet($mock, new ArrayTokenStore(new StoredToken('t')))
        ->customers()
        ->list();

    expect($customers)->toHaveCount(3)
        ->and($customers[0])->toBeInstanceOf(Customer::class)
        ->and($customers[0]->id)->toBe('11111')
        ->and($customers[0]->name)->toBe('Customer 1')
        ->and($customers[0]->number)->toBe('111')
        ->and($customers[0]->product)->toBe('Telematics')
        ->and($customers[2]->id)->toBe('33333')
        ->and($customers[2]->product)->toBe('Fuel');
});

it('requests the customers endpoint with a trailing slash', function () {
    expect((new GetCustomers())->resolveEndpoint())
        ->toBe('/vapi/v1/accounts/users/customers/');
});

it('returns an empty list for an empty response', function () {
    $mock = new MockClient([
        GetCustomers::class => MockResponse::make([]),
    ]);

    expect(velocityFleet($mock, new ArrayTokenStore(new StoredToken('t')))->customers()->list())
        ->toBe([]);
});
