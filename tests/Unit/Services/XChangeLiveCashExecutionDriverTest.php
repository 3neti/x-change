<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\XChange\Contracts\ExecutionCashDisbursementPollerContract;
use LBHurtado\XChange\Services\Execution\XChangeLiveCashExecutionDriver;

it('delegates redemption to the voucher default driver and returns reconciled live cash execution metadata', function () {
    $default = Mockery::mock(DefaultExecutionDriver::class);
    $default
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function (ExecutionContextData $context): bool {
            return $context->meta['mobile'] === '09173011987'
                && $context->meta['bank_account'] === 'GXCHPHM2XXX:09173011987'
                && $context->meta['amount'] === 75
                && $context->meta['inputs'] === [];
        })
        ->andReturn(ExecutionResultData::succeeded('default', [
            'voucher_code' => 'TEST-LIVE',
        ]));

    $poller = Mockery::mock(ExecutionCashDisbursementPollerContract::class);
    $poller
        ->shouldReceive('poll')
        ->once()
        ->with('TEST-LIVE', [
            'timeout' => 30,
            'poll' => 1,
        ])
        ->andReturn([
            'voucher_code' => 'TEST-LIVE',
            'current_status' => 'succeeded',
            'provider' => 'netbank',
            'provider_reference' => 'TEST-LIVE-09173011987',
            'provider_transaction_id' => 'TXN-LIVE',
            'reference_number' => 'REF-LIVE',
            'settlement_rail' => 'INSTAPAY',
            'destination_account' => [
                'bank_code' => 'GXCHPHM2XXX',
                'account_number_masked' => '*******1987',
            ],
        ]);

    $result = (new XChangeLiveCashExecutionDriver($default, $poller))->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '09173011987']),
        voucherCode: 'TEST-LIVE',
        meta: [
            'operation' => 'claim_transfer',
            'claim' => [
                'mobile' => '09173011987',
                'amount' => 75,
                'bank_account' => [
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09173011987',
                ],
                'inputs' => [],
            ],
            'poll' => [
                'timeout' => 30,
                'poll' => 1,
            ],
        ],
    ));

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('x_change_live_cash')
        ->and($result->status)->toBe('succeeded')
        ->and($result->events)->toContain('x_change_live_cash.redemption_requested')
        ->and($result->events)->toContain('x_change_live_cash.redemption_succeeded')
        ->and($result->events)->toContain('x_change_live_cash.disbursement_polled')
        ->and($result->providerReferences)
        ->toContain([
            'type' => 'provider_transaction_id',
            'value' => 'TXN-LIVE',
        ])
        ->and($result->metadata['provider'])->toBe('netbank')
        ->and($result->metadata['destination_account']['account_number_masked'])->toBe('*******1987');
});

it('fails when the voucher default driver rejects redemption before polling', function () {
    $default = Mockery::mock(DefaultExecutionDriver::class);
    $default
        ->shouldReceive('execute')
        ->once()
        ->andReturn(ExecutionResultData::failed('default', 'compatibility_redemption_rejected'));

    $poller = Mockery::mock(ExecutionCashDisbursementPollerContract::class);
    $poller->shouldNotReceive('poll');

    $result = (new XChangeLiveCashExecutionDriver($default, $poller))->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '09173011987']),
        voucherCode: 'TEST-LIVE',
    ));

    expect($result->successful)->toBeFalse()
        ->and($result->driver)->toBe('x_change_live_cash')
        ->and($result->failure)->toBe('compatibility_redemption_rejected')
        ->and($result->events)->toContain('x_change_live_cash.redemption_rejected');
});
