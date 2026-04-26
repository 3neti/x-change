<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\Cash\Exceptions\WithdrawalApprovalRequired;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;

class AuthorizeWithdrawalOtpStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public function __construct(
        protected WithdrawalOtpApprovalServiceContract $otp,
    ) {}

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::CASH_DOMAIN;
    }

    public static function description(): string
    {
        return 'Authorize withdrawal using OTP approval before cash-domain authorization policy proceeds.';
    }

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return (bool) data_get($context->payload, 'authorization.otp_required', false);
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        $mobile = $context->contact?->mobile
            ?? data_get($context->payload, 'mobile');

        if (! $mobile) {
            throw new \LogicException('Mobile is required before OTP withdrawal authorization.');
        }

        $reference = (string) ($context->voucher->code ?? $context->voucher->id);

        $code = data_get($context->payload, 'authorization.otp_code');

        if (! $code) {
            $this->otp->request(
                mobile: (string) $mobile,
                reference: $reference,
                context: [
                    'voucher_id' => $context->voucher->id,
                    'amount' => $context->withdrawAmount,
                ],
            );

            throw new WithdrawalApprovalRequired('OTP approval is required for this withdrawal.');
        }

        $verified = $this->otp->verify(
            mobile: (string) $mobile,
            reference: $reference,
            code: (string) $code,
            context: [
                'voucher_id' => $context->voucher->id,
                'amount' => $context->withdrawAmount,
            ],
        );

        if (! $verified) {
            throw new WithdrawalApprovalRequired('Invalid OTP approval code.');
        }

        data_set($context->payload, 'authorization.approved', true);
        data_set($context->payload, 'authorization.otp_verified', true);

        return $next($context);
    }
}
