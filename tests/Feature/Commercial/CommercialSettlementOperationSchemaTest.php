<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

it('installs additive commercial settlement operation tables and capabilities', function (): void {
    expect(Schema::hasColumns('x_change_commercial_provider_cost_batches', [
        'reference',
        'evidence_reference',
        'expected_amount_minor',
        'observed_amount_minor',
        'variance_amount_minor',
        'status',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('x_change_partner_commission_payout_batches', [
            'reference',
            'partner_reference',
            'destination',
            'maker_type',
            'checker_type',
            'provider_transaction_id',
            'status',
        ]))->toBeTrue()
        ->and(array_column(CommercialOperatorCapability::cases(), 'value'))->toContain(
            'commercial.provider_costs.reconcile',
            'commercial.commissions.request',
            'commercial.commissions.approve',
            'commercial.commissions.execute',
        );
});

it('encrypts commission payout destinations at rest', function (): void {
    $batch = PartnerCommissionPayoutBatch::query()->create([
        'reference' => 'commission-payout:test-encryption',
        'partner_reference' => 'partner:test',
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'position_reference' => 'position:test',
        'amount_minor' => 1_00,
        'currency' => 'PHP',
        'status' => PartnerCommissionPayoutBatchStatus::AwaitingApproval,
        'destination' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
            'recipient_name' => 'Test Partner',
            'mobile' => '09171234567',
        ],
        'destination_hash' => hash('sha256', 'test-destination'),
        'destination_summary' => 'GCash · ••••4567',
        'request_idempotency_key' => 'commission-payout:test-encryption',
        'request_hash' => hash('sha256', 'test-request'),
        'metadata' => [],
        'period_started_at' => now()->subDay(),
        'period_ended_at' => now(),
        'requested_at' => now(),
    ]);

    $raw = DB::table('x_change_partner_commission_payout_batches')
        ->where('id', $batch->getKey())
        ->value('destination');

    expect($raw)->not->toContain('09171234567')
        ->and($batch->fresh()->destination)->toMatchArray([
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ]);
});
