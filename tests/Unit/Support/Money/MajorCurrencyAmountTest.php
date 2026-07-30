<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Money\MajorCurrencyAmount;

it('converts familiar peso input into exact minor units', function (string $value, int $minor) {
    expect(MajorCurrencyAmount::toMinor($value))->toBe($minor);
})->with([
    ['500', 50_000],
    ['500.5', 50_050],
    ['1,250.50', 125_050],
    ['₱ 1,250.50', 125_050],
    ['PHP 1,250.50', 125_050],
]);
