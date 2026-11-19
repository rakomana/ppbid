<?php

use App\Support\Money;

it('formats rand with a thousands separator and two decimals', function (mixed $amount, string $expected) {
    expect(Money::format($amount))->toBe($expected);
})->with([
    'integer' => [1250, 'R1,250.00'],
    'decimal string from the database' => ['4200.50', 'R4,200.50'],
    'float' => [99.9, 'R99.90'],
    'cents' => [0.5, 'R0.50'],
    'millions' => [1234567.891, 'R1,234,567.89'],
    'zero' => [0, 'R0.00'],
]);
