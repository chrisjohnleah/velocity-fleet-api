<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Data;

use ChrisJohnLeah\VelocityFleet\Data\Concerns\MapsAttributes;

/**
 * A customer linked to the authenticated user.
 *
 * In the API response the customers are keyed by their unique identifier — it is
 * this {@see $id} (not {@see $number}) that the device-positions endpoint expects.
 */
final readonly class Customer
{
    use MapsAttributes;

    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $number = null,
        public ?string $product = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::string($data, 'id'),
            name: self::string($data, 'name'),
            number: self::string($data, 'number'),
            product: self::string($data, 'product'),
        );
    }
}
