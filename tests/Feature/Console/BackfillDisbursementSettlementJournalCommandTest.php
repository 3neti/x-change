<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('previews and idempotently backfills an explicitly selected finalized disbursement', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(amount: 25));
    $reconciliation = DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-09467438575-S1',
        'provider_transaction_id' => '409729887',
        'status' => 'succeeded',
        'internal_status' => 'finalized',
        'amount' => 25,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******8575',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'attempted_at' => now()->subMinute(),
        'completed_at' => now(),
        'needs_review' => false,
    ]);

    Artisan::call('x-change:treasury:backfill-disbursement-settlement-journal', [
        '--code' => [$voucher->code],
        '--json' => true,
    ]);
    $preview = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($preview)->toMatchArray([
        'status' => 'preview',
        'candidate_count' => 1,
        'missing_count' => 1,
        'recorded_count' => 0,
        'committed' => false,
        'provider_calls' => false,
        'treasury_changed' => false,
    ])->and(ExecutionJournalEntry::query()->count())->toBe(0);

    Artisan::call('x-change:treasury:backfill-disbursement-settlement-journal', [
        '--reconciliation' => [(string) $reconciliation->getKey()],
        '--commit' => true,
        '--json' => true,
    ]);
    $committed = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($committed)->toMatchArray([
        'status' => 'completed',
        'candidate_count' => 1,
        'missing_count' => 1,
        'recorded_count' => 1,
        'committed' => true,
        'provider_calls' => false,
        'treasury_changed' => false,
    ])->and(ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.disbursement.settled')
        ->where('subject_id', (string) $voucher->getKey())
        ->count())->toBe(1);

    Artisan::call('x-change:treasury:backfill-disbursement-settlement-journal', [
        '--code' => [$voucher->code],
        '--commit' => true,
        '--json' => true,
    ]);
    $replay = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($replay)->toMatchArray([
        'status' => 'completed',
        'candidate_count' => 1,
        'missing_count' => 0,
        'recorded_count' => 0,
        'committed' => true,
    ])->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('refuses an unscoped commit', function (): void {
    $this->artisan('x-change:treasury:backfill-disbursement-settlement-journal', [
        '--commit' => true,
        '--json' => true,
    ])->assertFailed();

    expect(ExecutionJournalEntry::query()->count())->toBe(0);
});
