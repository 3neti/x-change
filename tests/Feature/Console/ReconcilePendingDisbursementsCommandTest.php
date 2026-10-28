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

it('limits status-only reconciliation to the requested voucher and never submits intents', function (): void {
    foreach ([['CHOSEN', 'pending'], ['OTHER', 'pending'], ['CHOSEN', 'intent']] as [$code, $status]) {
        DisbursementReconciliation::query()->create([
            'voucher_id' => 10, 'voucher_code' => $code, 'claim_type' => 'value_flow',
            'provider' => 'netbank', 'provider_reference' => $code.$status,
            'provider_transaction_id' => $status === 'pending' ? $code : null,
            'status' => $status, 'internal_status' => 'reserved', 'amount' => 30,
            'currency' => 'PHP', 'attempt_count' => 1, 'needs_review' => false,
        ]);
    }
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldReceive('reconcile')->once()
        ->with(Mockery::on(fn ($row) => $row->voucher_code === 'CHOSEN' && $row->status === 'pending'))
        ->andReturn(['updated' => true, 'resolved_status' => 'succeeded']);
    app()->instance(DisbursementReconciliationContract::class, $service);
    $this->artisan('xchange:reconcile:pending', ['--voucher' => 'chosen', '--status-only' => true])
        ->expectsOutput('Processed: 1')->assertSuccessful();
    expect(DisbursementReconciliation::query()->where('status', 'intent')->count())->toBe(1);
});
