<?php

declare(strict_types=1);

use ChrisJohnLeah\VelocityFleet\Data\Concerns\MapsAttributes;

/**
 * Exposes the protected extractors so the coercion rules can be asserted
 * directly, independent of any particular DTO.
 */
function attributeMapper(): object
{
    return new class () {
        use MapsAttributes;

        /** @param array<string, mixed> $data */
        public function mapFloat(array $data, string $key): ?float
        {
            return self::float($data, $key);
        }

        /** @param array<string, mixed> $data */
        public function mapInteger(array $data, string $key): ?int
        {
            return self::integer($data, $key);
        }

        /**
         * @param  array<string, mixed>  $data
         * @return list<int>
         */
        public function mapIntList(array $data, string $key): array
        {
            return self::intList($data, $key);
        }

        /** @param array<string, mixed> $data */
        public function mapBoolean(array $data, string $key): ?bool
        {
            return self::boolean($data, $key);
        }
    };
}

it('coerces numeric-string fields into floats', function () {
    $mapper = attributeMapper();

    expect($mapper->mapFloat(['v' => '52.4862'], 'v'))->toBe(52.4862)
        ->and($mapper->mapFloat(['v' => '-1.89'], 'v'))->toBe(-1.89)
        ->and($mapper->mapFloat(['v' => 317.0], 'v'))->toBe(317.0)
        ->and($mapper->mapFloat(['v' => 0], 'v'))->toBe(0.0)
        ->and($mapper->mapFloat(['v' => 'not-a-number'], 'v'))->toBeNull()
        ->and($mapper->mapFloat(['v' => null], 'v'))->toBeNull()
        ->and($mapper->mapFloat([], 'v'))->toBeNull();
});

it('coerces the unix timestamp string into an integer but rejects fractions', function () {
    $mapper = attributeMapper();

    expect($mapper->mapInteger(['t' => '1726233388'], 't'))->toBe(1726233388)
        ->and($mapper->mapInteger(['t' => 99999], 't'))->toBe(99999)
        ->and($mapper->mapInteger(['t' => '42.5'], 't'))->toBeNull()
        ->and($mapper->mapInteger(['t' => 'nope'], 't'))->toBeNull()
        ->and($mapper->mapInteger([], 't'))->toBeNull();
});

it('builds clean integer lists from group id arrays', function () {
    $mapper = attributeMapper();

    expect($mapper->mapIntList(['g' => [99999, '88888']], 'g'))->toBe([99999, 88888])
        ->and($mapper->mapIntList(['g' => []], 'g'))->toBe([])
        // Non-integer members are dropped rather than corrupting the list.
        ->and($mapper->mapIntList(['g' => [1, 'x', 2.5, 3]], 'g'))->toBe([1, 3])
        ->and($mapper->mapIntList(['g' => 'not-an-array'], 'g'))->toBe([])
        ->and($mapper->mapIntList([], 'g'))->toBe([]);
});

it('tolerates common boolean encodings', function () {
    $mapper = attributeMapper();

    expect($mapper->mapBoolean(['b' => true], 'b'))->toBeTrue()
        ->and($mapper->mapBoolean(['b' => false], 'b'))->toBeFalse()
        // Integer and string encodings a JSON API might send.
        ->and($mapper->mapBoolean(['b' => 1], 'b'))->toBeTrue()
        ->and($mapper->mapBoolean(['b' => 0], 'b'))->toBeFalse()
        ->and($mapper->mapBoolean(['b' => 'true'], 'b'))->toBeTrue()
        ->and($mapper->mapBoolean(['b' => '0'], 'b'))->toBeFalse()
        // Genuinely non-boolean values stay null rather than guessing.
        ->and($mapper->mapBoolean(['b' => 'nonsense'], 'b'))->toBeNull()
        ->and($mapper->mapBoolean(['b' => null], 'b'))->toBeNull()
        ->and($mapper->mapBoolean([], 'b'))->toBeNull();
});
