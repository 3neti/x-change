<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Banking\PayoutBankSourceSnapshot;

it('encrypts a payout source and reuses it on replay', function () {
    $payout = DisbursementReconciliation::query()->create(['voucher_code' => 'BANK-SOURCE', 'status' => 'intent']);
    $service = app(PayoutBankSourceSnapshot::class);
    $snapshot = ['provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '12345'];
    $calls = 0;
    $first = $service->remember($payout->id, function () use (&$calls, $snapshot): array {
        $calls++;

        return $snapshot;
    });
    $again = $service->remember($payout->id, function () use (&$calls): array {
        $calls++;

        return [];
    });
    $stored = DB::table('disbursement_reconciliations')->where('id', $payout->id)->value('bank_source_snapshot');

    expect($first)->toBe($snapshot)->and($again)->toBe($snapshot)->and($calls)->toBe(1)
        ->and($stored)->not->toContain('991100001234')->not->toContain('12345')
        ->and($payout->fresh()->toArray())->not->toHaveKey('bank_source_snapshot');
});

it('does not freeze an incomplete payout source', function () {
    $payout = DisbursementReconciliation::query()->create(['voucher_code' => 'BANK-INCOMPLETE', 'status' => 'intent']);
    expect(fn () => app(PayoutBankSourceSnapshot::class)->remember($payout->id, fn (): array => ['provider' => 'netbank']))
        ->toThrow(ValidationException::class);
    expect($payout->fresh()->bank_source_snapshot)->toBeNull();
});
