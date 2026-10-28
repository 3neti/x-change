<?php

declare(strict_types=1);

use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherCollection;

it('shows recorded collection separately from masked destination delivery', function (string $status, string $internalStatus, string $delivery): void {
    $this->withoutMiddleware(ShareXChangeBranding::class);
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 30, 'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    VoucherCollection::query()->create([
        'voucher_id' => $voucher->id, 'collection_number' => 1, 'status' => 'collected',
        'requested_amount_minor' => 3000, 'collected_amount_minor' => 3000,
        'currency' => 'PHP', 'provider' => 'netbank', 'completed_at' => now(),
        'meta' => ['posting' => ['payout_reference' => 'DELIVERY-1']],
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->id, 'voucher_code' => $voucher->code,
        'claim_type' => 'value_flow', 'provider' => 'netbank', 'provider_reference' => 'DELIVERY-1',
        'status' => $status, 'internal_status' => $internalStatus, 'amount' => 30, 'currency' => 'PHP',
        'account_number_masked' => '*******9999', 'bank_code' => 'GXCHPHM2XXX',
        'meta' => ['account_ciphertext' => 'SECRET-MUST-NOT-LEAK'],
    ]);
    $response = $this->withHeader('X-Inertia', 'true')->get(route('x-change.pay.show', $voucher->code));
    $response->assertOk()->assertJsonPath('props.payment.is_fully_paid', true)
        ->assertJsonPath('props.payment.can_create_attempt', false)
        ->assertJsonPath('props.payment.receipt.payments.0.delivery.status', $delivery)
        ->assertJsonPath('props.payment.receipt.payments.0.delivery.account', '*******9999')
        ->assertDontSee('SECRET-MUST-NOT-LEAK');
})->with([
    ['pending', 'reserved', 'pending'],
    ['succeeded', 'reserved', 'pending'],
    ['succeeded', 'finalized', 'delivered'],
    ['failed', 'recovery_opened', 'review'],
    ['unknown', 'reserved', 'review'],
]);
