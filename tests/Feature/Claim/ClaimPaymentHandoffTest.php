<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherCollection;

it('hands a collectible Pay Code from the canonical claim page to the payment page', function (): void {
    $voucher = claimPaymentHandoffVoucher();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => strtolower((string) $voucher->code)]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/PaymentHandoff')
        ->assertJsonPath('props.code', (string) $voucher->code)
        ->assertJsonPath('props.payment_url', route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertJsonPath('props.is_fully_collected', false);

    expect(VoucherClaim::query()->count())->toBe(0)
        ->and(PaymentAttempt::query()->count())->toBe(0);
});

it('hands a collectible Pay Code from the query claim entry to the payment page without starting form flow', function (): void {
    $voucher = claimPaymentHandoffVoucher();
    $formFlow = Mockery::mock(FormFlowService::class);
    $formFlow->shouldReceive('startFlow')->never();
    $this->app->instance(FormFlowService::class, $formFlow);

    $this->withHeader('X-Inertia', 'true')
        ->get('/x/claim?code='.strtolower((string) $voucher->code))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/PaymentHandoff')
        ->assertJsonPath('props.code', (string) $voucher->code)
        ->assertJsonPath('props.payment_url', route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertJsonPath('props.is_fully_collected', false);

    expect(VoucherClaim::query()->count())->toBe(0)
        ->and(PaymentAttempt::query()->count())->toBe(0);
});

it('shows a fully paid collectible Pay Code variant without a payment call to action', function (): void {
    $voucher = claimPaymentHandoffVoucher(targetAmount: 100.00);
    claimPaymentHandoffCollection($voucher, 100.00);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/PaymentHandoff')
        ->assertJsonPath('props.code', (string) $voucher->code)
        ->assertJsonPath('props.payment_url', null)
        ->assertJsonPath('props.is_fully_collected', true);
});

it('keeps redeemable Pay Codes on the canonical claim experience', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'disbursable',
        ],
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.initial_code', (string) $voucher->code);
});

it('keeps redeemable Pay Codes on the query claim start behavior', function (): void {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'disbursable',
        ],
    ]));

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect();
});

it('keeps settlement-capable Pay Codes on the canonical claim experience', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'settlement',
        ],
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.initial_code', (string) $voucher->code);
});

it('keeps settlement-capable Pay Codes on the query claim start behavior', function (): void {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'settlement',
        ],
    ]));

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect();
});

it('preserves the hard error when a non-disbursable Pay Code is not collectible', function (): void {
    config()->set('x-change.voucher_flow_types.canonical.collectible.can_collect', false);

    $voucher = claimPaymentHandoffVoucher();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Error')
        ->assertJsonPath('props.message', 'This Pay Code accepts payment and cannot be claimed.')
        ->assertJsonPath('props.code', (string) $voucher->code);
});

function claimPaymentHandoffVoucher(float $targetAmount = 100.00): Voucher
{
    $issuer = actingAsTestUser();

    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => $targetAmount,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->id,
                'collection_wallet_id' => $issuer->wallet->id,
            ],
        ],
    ));
}

function claimPaymentHandoffCollection(Voucher $voucher, float $amount): VoucherCollection
{
    return VoucherCollection::query()->create([
        'voucher_id' => $voucher->getKey(),
        'collection_number' => VoucherCollection::query()
            ->where('voucher_id', $voucher->getKey())
            ->count() + 1,
        'status' => 'collected',
        'requested_amount_minor' => (int) round($amount * 100),
        'collected_amount_minor' => (int) round($amount * 100),
        'currency' => 'PHP',
        'provider' => 'test',
        'provider_reference' => 'claim-payment-handoff',
        'provider_transaction_id' => 'claim-payment-handoff-'.str()->uuid(),
        'idempotency_key' => 'claim-payment-handoff:'.str()->uuid(),
        'completed_at' => now(),
    ]);
}
