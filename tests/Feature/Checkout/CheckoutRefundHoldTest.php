<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Checkout\OpenCheckoutRefundHold;
use LBHurtado\XChange\Actions\Checkout\ReleaseCheckoutRefundHold;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

it('opens one idempotent client-funds Pay Code refund hold for a settled checkout', function (): void {
    [$checkout, $principal] = refundHoldCheckout();

    $action = app(OpenCheckoutRefundHold::class);
    $case = $action->handle($checkout, 1_000, 'KYC could not be completed.', holdUntil: Carbon::now()->addDay());
    $replayed = $action->handle($checkout->refresh(), 1_000, 'Duplicate terminal failure.');

    expect($case->getKey())->toBe($replayed->getKey())
        ->and($case->status)->toBe('held')
        ->and($case->disposition)->toBe('pay_code')
        ->and($case->amount_minor)->toBe(1_000)
        ->and($case->metadata['amount_component'])->toBe('client_funds')
        ->and($checkout->refresh()->events()->where('event_type', 'pay_code_refund_hold_opened')->count())->toBe(1);
});

it('releases an expired Pay Code refund hold to eligibility without issuing a Pay Code', function (): void {
    [$checkout] = refundHoldCheckout();
    $case = app(OpenCheckoutRefundHold::class)->handle(
        $checkout,
        1_000,
        'Terminal onboarding failure.',
        holdUntil: Carbon::now()->subSecond(),
    );

    $released = app(ReleaseCheckoutRefundHold::class)->handle($case);

    expect($released->status)->toBe('eligible')
        ->and($released->refund_pay_code_id)->toBeNull()
        ->and($released->eligible_at)->not->toBeNull();
});

it('rejects a refund hold without a settled payment', function (): void {
    $principal = refundHoldPrincipal();
    $order = refundHoldOrder($principal);
    $checkout = app(CheckoutLifecycle::class)->place($order);

    expect(fn () => app(OpenCheckoutRefundHold::class)->handle($checkout, 1_000, 'No settlement.'))
        ->toThrow(ConflictHttpException::class);
});

it('routes a refund hold without a usable mobile to manual recovery', function (): void {
    [$checkout] = refundHoldCheckout();
    $checkout->forceFill([
        'visitor_mobile_ciphertext' => null,
        'visitor_mobile_hash' => null,
    ])->saveQuietly();

    $case = app(OpenCheckoutRefundHold::class)->handle($checkout, 1_000, 'No recipient mobile.');

    expect($case->status)->toBe('manual_recovery')
        ->and($case->metadata['recipient_mobile_available'])->toBeFalse();
});

/** @return array{0: Checkout, 1: CommercialPrincipal} */
function refundHoldCheckout(): array
{
    $principal = refundHoldPrincipal();
    $order = refundHoldOrder($principal);
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => 'account_funding',
        'expected_amount_minor' => 2_500,
        'currency' => 'PHP',
        'status' => 'settled',
        'idempotency_key_hash' => hash('sha256', uniqid('refund-intent-', true)),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => $principal::class,
        'created_by_id' => (string) $principal->getKey(),
        'expires_at' => now()->addHour(),
    ]);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', uniqid('refund-observation-', true)),
        'provider_code' => 'netbank',
        'provider_transaction_id' => uniqid('refund-payment-', true),
        'gross_amount_minor' => 2_500,
        'net_amount_minor' => 2_500,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', uniqid('refund-payload-', true)),
    ]);
    $settlement = FundingSettlement::query()->create([
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'account_reference' => $order->account_reference,
        'gross_amount_minor' => 2_500,
        'net_amount_minor' => 2_500,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'refund-inventory',
        'treasury_operation_reference' => 'refund-operation',
        'wallet_transaction_id' => 123456,
        'wallet_transaction_uuid' => '12345678-1234-1234-1234-123456789012',
        'settled_at' => now(),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey(), 'status' => 'issuance_attention'])->saveQuietly();
    $checkout = app(CheckoutLifecycle::class)->place($order);
    $checkout->forceFill([
        'visitor_mobile_ciphertext' => '639171234567',
        'visitor_mobile_hash' => hash_hmac('sha256', '639171234567', (string) config('app.key')),
    ])->saveQuietly();

    expect($settlement->exists)->toBeTrue();

    return [$checkout, $principal];
}

function refundHoldPrincipal(): CommercialPrincipal
{
    return CommercialPrincipal::query()->create([
        'reference' => uniqid('refund-principal-', true),
        'legal_name' => 'Refund Test Principal',
        'authorization_reference' => 'refund:test:v1',
        'active' => true,
        'metadata' => [],
    ]);
}

function refundHoldOrder(CommercialPrincipal $principal): PayCodeIssuanceFundingOrder
{
    return PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:refund-test',
        'issuer_type' => $principal::class,
        'issuer_id' => (string) $principal->getKey(),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'funding_basis' => 'full_amount',
        'instructions_ciphertext' => ['cash' => ['amount' => 25, 'currency' => 'PHP']],
        'instructions_fingerprint' => str_repeat('a', 64),
        'pricing_snapshot_ciphertext' => ['total' => 25],
        'pricing_fingerprint' => str_repeat('b', 64),
        'required_amount_minor' => 2_500,
        'reserved_client_funds_minor' => 0,
        'on_demand_amount_minor' => 2_500,
        'reconciliation_adjustment_minor' => 0,
        'expected_payment_minor' => 2_500,
        'currency' => 'PHP',
        'status' => 'awaiting_payment',
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', uniqid('refund-order-', true)),
        'idempotency_fingerprint' => str_repeat('d', 64),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'public.auto-register'],
    ]);
}
