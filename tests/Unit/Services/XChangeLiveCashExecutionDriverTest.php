<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ExecutionCashDisbursementPollerContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Services\Execution\XChangeLiveCashExecutionDriver;

it('uses the canonical claim pipeline for an amount slice and returns reconciled live cash execution metadata', function () {
    $default = Mockery::mock(DefaultExecutionDriver::class);
    $default->shouldNotReceive('execute');
    $claims = Mockery::mock(SubmitPayCodeClaim::class);
    $claims
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function (mixed $voucher, array $claim): bool {
            return $voucher instanceof Voucher
                && $claim['mobile'] === '09173011987'
                && $claim['bank_account']['account_number'] === '09173011987'
                && $claim['amount'] === 75
                && $claim['inputs'] === [];
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'TEST-LIVE',
            claim_type: 'withdraw',
            claimed: true,
            status: 'succeeded',
            requested_amount: 75,
            disbursed_amount: 75,
            currency: 'PHP',
            remaining_balance: 75,
        ));

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

    $voucher = new Voucher;
    $result = (new XChangeLiveCashExecutionDriver($default, $poller, $claims))->execute(new ExecutionContextData(
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
        voucher: $voucher,
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
    $claims = Mockery::mock(SubmitPayCodeClaim::class);
    $claims->shouldNotReceive('handle');

    $result = (new XChangeLiveCashExecutionDriver($default, $poller, $claims))->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '09173011987']),
        voucherCode: 'TEST-LIVE',
    ));

    expect($result->successful)->toBeFalse()
        ->and($result->driver)->toBe('x_change_live_cash')
        ->and($result->failure)->toBe('compatibility_redemption_rejected')
        ->and($result->events)->toContain('x_change_live_cash.redemption_rejected');
});
