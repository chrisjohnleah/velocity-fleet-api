<?php

declare(strict_types=1);

namespace ChrisJohnLeah\VelocityFleet\Data\Concerns;

use DateTimeImmutable;
use Throwable;

/**
 * Typed extractors that safely pull values out of Velocity's decoded JSON
 * (which PHPStan sees as mixed) into the strict property types of a DTO.
 */
trait MapsAttributes
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        // Velocity occasionally serialises numerics as strings (e.g. the unix
        // "timestamp"); accept clean numeric strings too.
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function integer(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        // Accept numeric strings/floats that represent a whole number (Velocity
        // sends the device "timestamp" as a string, and some ids as strings).
        if ((is_string($value) || is_float($value)) && is_numeric($value) && (float) (int) $value === (float) $value) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function boolean(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        // Tolerate the common JSON boolean encodings (1/0, "true"/"false",
        // "1"/"0"), mirroring the string-tolerant numeric helpers above.
        if (is_int($value) || is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function dateTime(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A nested object, as an array ready to hydrate a child DTO.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected static function nested(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * A list of nested objects, each as an array ready to hydrate a child DTO.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected static function nestedList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * A list of integers (e.g. device group ids, iso numbers). Tolerates whole-
     * number strings/floats, dropping anything that isn't cleanly an integer.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    protected static function intList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_int($item)) {
                $items[] = $item;
            } elseif ((is_string($item) || is_float($item)) && is_numeric($item) && (float) (int) $item === (float) $item) {
                $items[] = (int) $item;
            }
        }

        return $items;
    }

    /**
     * A raw, untyped value passed through as-is.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function raw(array $data, string $key): mixed
    {
        return $data[$key] ?? null;
    }
}
