<?php

declare(strict_types=1);

use ChrisJohnLeah\VelocityFleet\Exceptions\ApiException;

it('exposes the response headers it was given', function () {
    $exception = new ApiException('throttled', 429, null, ['Retry-After' => '60', 'X-Request-Id' => 'abc']);

    expect($exception->headers)->toBe(['Retry-After' => '60', 'X-Request-Id' => 'abc'])
        ->and($exception->header('retry-after'))->toBe('60') // case-insensitive
        ->and($exception->header('X-Request-Id'))->toBe('abc')
        ->and($exception->header('missing'))->toBeNull();
});

it('parses Retry-After in delay-seconds form', function () {
    expect((new ApiException('t', 429, null, ['Retry-After' => '120']))->retryAfter())->toBe(120)
        ->and((new ApiException('t', 429, null, ['Retry-After' => '0']))->retryAfter())->toBe(0);
});

it('parses Retry-After in HTTP-date form relative to now', function () {
    $now = new DateTimeImmutable('2026-06-09 12:00:00', new DateTimeZone('UTC'));
    $future = $now->modify('+120 seconds')->setTimezone(new DateTimeZone('UTC'));
    $exception = new ApiException('t', 429, null, ['Retry-After' => $future->format('D, d M Y H:i:s \G\M\T')]);

    expect($exception->retryAfter($now))->toBe(120);
});

it('never returns a negative Retry-After for a past HTTP-date', function () {
    $now = new DateTimeImmutable('2026-06-09 12:05:00', new DateTimeZone('UTC'));
    $past = $now->modify('-300 seconds')->setTimezone(new DateTimeZone('UTC'));
    $exception = new ApiException('t', 429, null, ['Retry-After' => $past->format('D, d M Y H:i:s \G\M\T')]);

    expect($exception->retryAfter($now))->toBe(0);
});

it('returns null Retry-After when the header is absent or unparseable', function () {
    expect((new ApiException('t', 429, null, []))->retryAfter())->toBeNull()
        ->and((new ApiException('t', 429, null, ['Retry-After' => 'soon']))->retryAfter())->toBeNull();
});
