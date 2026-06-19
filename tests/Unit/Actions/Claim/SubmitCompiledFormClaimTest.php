<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\BuildCompiledFormClaimPayload;
use LBHurtado\XChange\Actions\Claim\SubmitCompiledFormClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;

it('syncs compiled form claim evidence before submitting the claim', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09173011987',
        ],
    );

    $order = [];

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence
        ->shouldReceive('sync')
        ->once()
        ->with([
            'source' => 'compiled_form',
            'code' => $voucher->code,
            'voucher_id' => $voucher->getKey(),
            'mobile' => '09173011987',
            'country' => 'PH',
            'bank_code' => null,
            'account_number' => null,
            'amount' => null,
            'settlement_rail' => null,
            'inputs' => [
                'mobile' => '09173011987',
            ],
        ])
        ->andReturnUsing(function () use (&$order): void {
            $order[] = 'sync';
        });

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->with($voucher, [
            'source' => 'compiled_form',
            'code' => $voucher->code,
            'voucher_id' => $voucher->getKey(),
            'mobile' => '09173011987',
            'country' => 'PH',
            'bank_code' => null,
            'account_number' => null,
            'amount' => null,
            'settlement_rail' => null,
            'inputs' => [
                'mobile' => '09173011987',
            ],
        ])
        ->andReturnUsing(function () use (&$order, $voucher) {
            $order[] = 'submit';

            return new SubmitPayCodeClaimResultData(
                voucher_code: $voucher->code,
                claim_type: 'withdraw',
                claimed: true,
                status: 'success',
                requested_amount: null,
                disbursed_amount: null,
                currency: null,
                remaining_balance: null,
                fully_claimed: true,
                disbursement: null,
                messages: [],
            );
        });

    $action = new SubmitCompiledFormClaim(
        new BuildCompiledFormClaimPayload,
        $evidence,
        $submitPayCodeClaim,
    );

    $result = $action->handle($voucher, $prepared);

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class)
        ->and($result->voucher_code)->toBe($voucher->code)
        ->and($result->status)->toBe('success')
        ->and($order)->toBe(['sync', 'submit']);
});

it('bubbles up evidence sync failures before submitting the claim', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09173011987',
        ],
    );

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence
        ->shouldReceive('sync')
        ->once()
        ->andThrow(new RuntimeException('Evidence sync failed.'));

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldNotReceive('handle');

    $action = new SubmitCompiledFormClaim(
        new BuildCompiledFormClaimPayload,
        $evidence,
        $submitPayCodeClaim,
    );

    expect(fn () => $action->handle($voucher, $prepared))
        ->toThrow(RuntimeException::class, 'Evidence sync failed.');
});

it('bubbles up redemption submission failures', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09173011987',
        ],
    );

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence
        ->shouldReceive('sync')
        ->once();

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Compiled claim failed.'));

    $action = new SubmitCompiledFormClaim(
        new BuildCompiledFormClaimPayload,
        $evidence,
        $submitPayCodeClaim,
    );

    expect(fn () => $action->handle($voucher, $prepared))
        ->toThrow(RuntimeException::class, 'Compiled claim failed.');
});
