<?php

declare(strict_types=1);

use LBHurtado\Cash\Exceptions\WithdrawalApprovalRequired;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalOtpStep;

it('requests otp and returns approval required when otp code is missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
    ));

    $contact = new Contact;
    $contact->mobile = '09173011987';

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [
            'authorization' => [
                'otp_required' => true,
            ],
        ],
        contact: $contact,
    );

    $context->withdrawAmount = 1500.00;

    $otp = Mockery::mock(WithdrawalOtpApprovalServiceContract::class);

    $otp->shouldReceive('request')
        ->once()
        ->withArgs(fn ($mobile, $reference, $otpContext) =>
            $mobile === '09173011987'
            && $reference === (string) $voucher->code
            && $otpContext['amount'] === 1500.00
        )
        ->andReturn(['status' => 'requested']);

    $step = new AuthorizeWithdrawalOtpStep($otp);

    $step->handle($context, fn ($context) => $context);
})->throws(WithdrawalApprovalRequired::class, 'OTP approval is required for this withdrawal.');

it('fails when otp code is invalid', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
    ));

    $contact = new Contact;
    $contact->mobile = '09173011987';

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [
            'authorization' => [
                'otp_required' => true,
                'otp_code' => '111111',
            ],
        ],
        contact: $contact,
    );

    $context->withdrawAmount = 1500.00;

    $otp = Mockery::mock(WithdrawalOtpApprovalServiceContract::class);

    $otp->shouldReceive('verify')
        ->once()
        ->andReturnFalse();

    $step = new AuthorizeWithdrawalOtpStep($otp);

    $step->handle($context, fn ($context) => $context);
})->throws(WithdrawalApprovalRequired::class, 'Invalid OTP approval code.');

it('marks authorization approved when otp code is valid', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
    ));

    $contact = new Contact;
    $contact->mobile = '09173011987';

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [
            'authorization' => [
                'otp_required' => true,
                'otp_code' => '000000',
            ],
        ],
        contact: $contact,
    );

    $context->withdrawAmount = 1500.00;

    $otp = Mockery::mock(WithdrawalOtpApprovalServiceContract::class);

    $otp->shouldReceive('verify')
        ->once()
        ->andReturnTrue();

    $step = new AuthorizeWithdrawalOtpStep($otp);

    $result = $step->handle($context, fn ($context) => $context);

    expect($result)->toBe($context)
        ->and(data_get($context->payload, 'authorization.approved'))->toBeTrue()
        ->and(data_get($context->payload, 'authorization.otp_verified'))->toBeTrue();
});
