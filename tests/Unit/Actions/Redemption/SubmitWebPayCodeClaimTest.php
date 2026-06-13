<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;

it('runs web claim execution with deferred Paynamics OTP resolver', function () {
    $voucher = new Voucher;
    $voucher->code = 'WEB-CLAIM-1234';

    $submit = Mockery::mock(SubmitPayCodeClaim::class);

    $submit->shouldReceive('handle')
        ->once()
        ->with($voucher, ['mobile' => '639171234567'])
        ->andReturnUsing(function () use ($voucher) {
            expect(app(ConstellationOtpResolver::class)::class)
                ->toBe(DeferredOtpResolver::class);

            return new SubmitPayCodeClaimResultData(
                voucher_code: (string) $voucher->code,
                claim_type: 'withdraw',
                claimed: false,
                status: 'received',
                requested_amount: null,
                disbursed_amount: null,
                currency: 'PHP',
                remaining_balance: null,
                fully_claimed: false,
                disbursement: null,
                messages: ['Claim received.'],
            );
        });

    $result = app(SubmitWebPayCodeClaim::class, [
        'submitPayCodeClaim' => $submit,
        'deferredOtpResolver' => app(UseDeferredPaynamicsOtpResolver::class),
    ])->handle($voucher, [
        'mobile' => '639171234567',
    ]);

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class)
        ->and($result->status)->toBe('received');
});
