<?php

declare(strict_types=1);

use LBHurtado\Cash\Contracts\CashClaimantAuthorizationContract;
use LBHurtado\Cash\Contracts\CashWithdrawalEligibilityContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalPipeline;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AssertWithdrawalEligibilityStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalClaimantStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\ResolveWithdrawalClaimantStep;

it('runs the first withdrawal pipeline steps', function () {
    $voucher = issueVoucher();

    $eligibility = Mockery::mock(CashWithdrawalEligibilityContract::class);
    $eligibility->shouldReceive('assertEligible')
        ->once()
        ->withArgs(fn ($instrument) => $instrument->getInstrumentId() === $voucher->id);

    $authorization = Mockery::mock(CashClaimantAuthorizationContract::class);
    $authorization->shouldReceive('authorize')
        ->once()
        ->withArgs(fn ($instrument, $claimant) => $instrument->getInstrumentId() === $voucher->id);

    $pipeline = new WithdrawalPipeline(
        pipeline: app(\Illuminate\Pipeline\Pipeline::class),
        steps: [
            new ResolveWithdrawalClaimantStep(),
            new AssertWithdrawalEligibilityStep($eligibility),
            new AuthorizeWithdrawalClaimantStep($authorization),
        ],
    );

    $context = $pipeline->process(new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [
            'mobile' => '639171234567',
            'recipient_country' => 'PH',
        ],
    ));

    expect($context->voucher->id)->toBe($voucher->id)
        ->and($context->contact)->not->toBeNull()
        ->and($context->contact->mobile)->toBe('09171234567');
});

it('fails when mobile is missing before eligibility and authorization', function () {
    $voucher = issueVoucher();

    $eligibility = Mockery::mock(CashWithdrawalEligibilityContract::class);
    $eligibility->shouldReceive('assertEligible')->never();

    $authorization = Mockery::mock(CashClaimantAuthorizationContract::class);
    $authorization->shouldReceive('authorize')->never();

    $pipeline = new WithdrawalPipeline(
        pipeline: app(\Illuminate\Pipeline\Pipeline::class),
        steps: [
            new ResolveWithdrawalClaimantStep(),
            new AssertWithdrawalEligibilityStep($eligibility),
            new AuthorizeWithdrawalClaimantStep($authorization),
        ],
    );

    $pipeline->process(new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    ));
})->throws(InvalidArgumentException::class, 'Mobile number is required.');
