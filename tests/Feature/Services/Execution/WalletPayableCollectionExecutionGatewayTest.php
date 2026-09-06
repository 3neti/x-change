<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\PayableCollectionRejectedException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Services\Execution\WalletPayableCollectionExecutionGateway;

it('rejects authorization when the execution context has no persisted voucher', function (): void {
    $wallets = Mockery::mock(VoucherCollectionWalletResolverContract::class);
    $wallets->shouldNotReceive('resolve');
    $collect = Mockery::mock(CollectVoucherFunds::class);
    $collect->shouldNotReceive('collectConfirmed');

    $gateway = new WalletPayableCollectionExecutionGateway($wallets, $collect);

    $gateway->authorize(new ExecutionContextData(
        contact: null,
        voucherCode: 'TEST',
    ), 'execution-null-voucher');
})->throws(
    PayableCollectionRejectedException::class,
    'requires a persisted Pay Code',
);

it('rejects collection before credit when the collection wallet is not authorized', function (): void {
    $voucher = issueVoucher(validVoucherInstructions());
    $wallets = Mockery::mock(VoucherCollectionWalletResolverContract::class);
    $wallets->shouldReceive('resolve')
        ->once()
        ->with($voucher)
        ->andThrow(new PayCodeWalletNotResolved('Foreign collection wallet.'));
    $collect = Mockery::mock(CollectVoucherFunds::class);
    $collect->shouldNotReceive('collectConfirmed');

    $gateway = new WalletPayableCollectionExecutionGateway($wallets, $collect);

    $gateway->authorize(payableCollectionExecutionContext($voucher), 'execution-unauthorized');
})->throws(
    PayableCollectionRejectedException::class,
    'collection wallet is not authorized',
);

it('delegates authoritative crediting to the existing collection action', function (): void {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        overrides: [
            'target_amount' => 50.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->getKey(),
                'collection_wallet_id' => $user->wallet->getKey(),
            ],
        ],
    ));
    $context = payableCollectionExecutionContext($voucher);
    $wallets = Mockery::mock(VoucherCollectionWalletResolverContract::class);
    $wallets->shouldReceive('resolve')
        ->once()
        ->with($voucher)
        ->andReturn($user->wallet);
    $collect = Mockery::mock(CollectVoucherFunds::class);
    $collect->shouldReceive('collectConfirmed')
        ->once()
        ->andReturnUsing(function ($actualVoucher, VoucherPaymentResultData $result, array $payload) use ($voucher): VoucherPaymentResultData {
            expect($actualVoucher->is($voucher))->toBeTrue()
                ->and($result->voucher_code)->toBe((string) $voucher->code)
                ->and($result->status)->toBe('succeeded')
                ->and($result->amount)->toBe(50.00)
                ->and($result->currency)->toBe('PHP')
                ->and($result->provider)->toBe('netbank')
                ->and($result->provider_reference)->toBe('payment-attempt:01TEST')
                ->and($result->provider_transaction_id)->toBe('provider-transaction-01')
                ->and($result->meta)->toBe([
                    'payment_attempt_reference' => 'payment-attempt:01TEST',
                    'provider_observation_id' => 42,
                    'verification_source' => 'provider_api',
                ])
                ->and($payload)->toBe([
                    'amount' => 50,
                    'currency' => 'PHP',
                    'status' => 'succeeded',
                    'provider' => 'netbank',
                    'provider_reference' => 'payment-attempt:01TEST',
                    'provider_transaction_id' => 'provider-transaction-01',
                    'idempotency_key' => 'payment-attempt:01TEST',
                ]);

            return new VoucherPaymentResultData(
                voucher_code: (string) $voucher->code,
                status: 'collected',
                amount: 50.00,
                meta: ['collection_id' => 321],
            );
        });

    $gateway = new WalletPayableCollectionExecutionGateway($wallets, $collect);

    expect($gateway->authorize($context, 'execution-01'))
        ->toBe(['collection_wallet_authorized' => true])
        ->and($gateway->credit($context, 5000, 'provider-transaction-01', 'execution-01'))
        ->toBe([
            'voucher_collection_id' => 321,
            'collection_status' => 'collected',
        ]);
});

function payableCollectionExecutionContext(Voucher $voucher): ExecutionContextData
{
    return new ExecutionContextData(
        contact: null,
        voucherCode: (string) $voucher->code,
        meta: [
            'operation' => 'collect',
            'amount_minor' => 5000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'provider_reference' => 'payment-attempt:01TEST',
            'provider_transaction_id' => 'provider-transaction-01',
            'provider_observation_id' => 42,
            'verification_source' => 'provider_api',
            'idempotency_key' => 'payment-attempt:01TEST',
        ],
        voucher: $voucher,
    );
}
