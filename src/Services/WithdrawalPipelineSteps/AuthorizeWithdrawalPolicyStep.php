<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\Cash\Contracts\CashWithdrawalAuthorizationPolicyContract;
use LBHurtado\Cash\Data\WithdrawalAuthorizationContextData;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;

class AuthorizeWithdrawalPolicyStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public function __construct(
        protected CashWithdrawalAuthorizationPolicyContract $authorizationPolicy,
    ) {}

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::CASH_DOMAIN;
    }

    public static function description(): string
    {
        return 'Authorize withdrawal against cash-domain approval policies such as thresholds and mandates.';
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->withdrawAmount === null) {
            throw new \LogicException('Withdrawal amount must be resolved before authorization policy.');
        }

        $instrument = new VoucherWithdrawableInstrumentAdapter($context->voucher);

        $this->authorizationPolicy->authorize(
            instrument: $instrument,
            context: new WithdrawalAuthorizationContextData(
                amount: $context->withdrawAmount,
                payload: $context->payload,
                claimantId: $context->contact?->id ? (string) $context->contact->id : null,
                vendorId: data_get($context->payload, 'vendor_id'),
                approvalThreshold: data_get($context->voucher->instructions ?? [], 'cash.approval_threshold'),
                approved: (bool) data_get($context->payload, 'authorization.approved', false),
            ),
        );

        return $next($context);
    }
}
