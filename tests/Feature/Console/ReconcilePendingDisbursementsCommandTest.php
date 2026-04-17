<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;

it('reconciles pending records through the console command', function () {
    $record = DisbursementReconciliation::query()->create([
        'voucher_id' => 10,
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider' => 'constellation',
        'provider_reference' => 'REF-001',
        'provider_transaction_id' => null,
        'transaction_uuid' => null,
        'status' => 'pending',
        'internal_status' => 'recorded',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '******4567',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'needs_review' => false,
        'attempted_at' => now(),
    ]);

    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldReceive('reconcile')
        ->once()
        ->with(Mockery::on(function ($row) use ($record) {
            return $row instanceof DisbursementReconciliation
                && $row->id === $record->id
                && $row->voucher_code === 'TEST-1234';
        }))
        ->andReturn([
            'updated' => true,
            'before_status' => 'pending',
            'fetched_status' => 'completed',
            'resolved_status' => 'succeeded',
            'reconciliation_id' => $record->id,
            'raw' => [
                'transaction_id' => 'TX-001',
                'uuid' => 'UUID-001',
            ],
            'needs_review' => false,
            'review_reason' => null,
            'trusted_failure' => true,
        ]);

    $this->app->instance(DisbursementReconciliationContract::class, $service);

    $this->artisan('xchange:reconcile:pending')
        ->expectsOutput('Processed: 1')
        ->expectsOutput('Updated: 1')
        ->expectsOutput("TEST-1234 [{$record->id}]: pending -> succeeded (updated)")
        ->assertSuccessful();
});
