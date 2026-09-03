<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

it('keeps the Pay Codes table projection query count bounded for realistic row counts', function () {
    $operator = actingAsTestUser();

    foreach (range(1, 150) as $index) {
        issueVoucher(validVoucherInstructions(
            amount: 10 + $index,
            overrides: [
                'prefix' => 'PERF',
                'rider' => [
                    'message' => "Performance row {$index}",
                ],
            ],
        ));
    }

    $queryCount = 0;
    DB::listen(static function () use (&$queryCount): void {
        $queryCount++;
    });

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forPayCodeList(new CockpitReadModelQueryData(
            operatorId: (string) $operator->getKey(),
            operatorType: $operator->getMorphClass(),
            actor: $operator,
            include: ['voucher', 'redeemer'],
        ));

    expect($readModel->records)
        ->toHaveCount(150)
        ->and($queryCount)
        ->toBeLessThan(80);
});
