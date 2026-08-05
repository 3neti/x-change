<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;

it('reconciles pending records through the console command', function () {
    $record = DisbursementReconciliation::query()->create([
        'voucher_id' => 10,
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider' => 'constellation',
        'provider_reference' => 'REF-001',
        'provider_transaction_id' => 'TX-001',
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

it('does not poll an internal reference that has no provider transaction identifier', function (): void {
    DisbursementReconciliation::query()->create([
        'voucher_id' => 11,
        'voucher_code' => 'TEST-NOT-SUBMITTED',
        'claim_type' => 'payout_recovery',
        'provider' => 'netbank',
        'provider_reference' => 'TEST-NOT-SUBMITTED-R1',
        'provider_transaction_id' => null,
        'status' => 'unknown',
        'internal_status' => 'recorded',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******3980',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'needs_review' => true,
        'attempted_at' => now(),
    ]);

    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldNotReceive('reconcile');
    $this->app->instance(DisbursementReconciliationContract::class, $service);

    $this->artisan('xchange:reconcile:pending', ['--json' => true])
        ->expectsOutput('{"processed":0,"updated":0,"results":[]}')
        ->assertSuccessful();
});

it('registers package-owned pending disbursement reconciliation every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:reconcile:pending');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->command)->toContain('xchange:reconcile:pending --limit=50');
});
