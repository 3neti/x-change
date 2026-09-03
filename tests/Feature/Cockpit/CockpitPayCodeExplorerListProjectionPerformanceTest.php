<?php

declare(strict_types=1);

use FrittenKeeZ\Vouchers\Config;
use Illuminate\Support\Facades\DB;
use LBHurtado\Contact\Models\Contact;
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

it('projects claimed party identity on the optimized Pay Codes table path', function () {
    $operator = actingAsTestUser();
    $voucher = issueVoucher();
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'name' => 'Leslie Chong',
        'bank_account' => 'GCASH:09171234567',
    ]);
    $redeemerClass = Config::model('redeemer');
    $redeemer = new $redeemerClass(['metadata' => []]);
    $redeemer->redeemer()->associate($contact);
    $voucher->redeemers()->save($redeemer);
    $voucher->forceFill(['redeemed_at' => now()])->save();

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forPayCodeList(new CockpitReadModelQueryData(
            operatorId: (string) $operator->getKey(),
            operatorType: $operator->getMorphClass(),
            actor: $operator,
            include: ['voucher', 'redeemer'],
        ));

    $record = collect($readModel->records)->sole();
    $payload = $record->toArray();

    expect($payload['party'])->toBe([
        'state' => 'claimed',
        'label' => 'Claimed by',
        'primary' => 'Leslie Chong',
        'secondary' => '•••• 4567',
        'masked' => true,
    ])
        ->and(json_encode($payload))->not->toContain('09171234567');
});
