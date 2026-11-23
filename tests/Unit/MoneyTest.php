<?php

use App\Billing\Money;

it('parses exact decimal strings into nanodollars', function (string $decimal, int $nanos) {
    expect(Money::nanosFromDecimal($decimal))->toBe($nanos);
})->with([
    ['0.046125000', 46_125_000],
    ['12.50', 12_500_000_000],
    ['0', 0],
    ['0.000000001', 1],
    ['-1.5', -1_500_000_000],
]);

it('refuses amounts it cannot represent exactly', function () {
    Money::nanosFromDecimal('0.0000000001');
})->throws(InvalidArgumentException::class);

it('refuses floats-as-strings and garbage', function () {
    Money::nanosFromDecimal('1e-9');
})->throws(InvalidArgumentException::class);

it('formats nanodollars as 9-decimal strings like Pixelforge', function () {
    expect(Money::toDecimal(373_875_000))->toBe('0.373875000')
        ->and(Money::toDecimal(0))->toBe('0.000000000')
        ->and(Money::toDecimal(-625))->toBe('-0.000000625');
});

it('formats for humans without rounding', function () {
    expect(Money::format(2_000_000_000))->toBe('$2.00')
        ->and(Money::format(46_125_000))->toBe('$0.046125')
        ->and(Money::format(625))->toBe('$0.000000625');
});
