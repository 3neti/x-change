<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;
use LogicException;
use LBHurtado\Cash\Contracts\CashClaimantAuthorizationContract;
use LBHurtado\XChange\Adapters\ContactClaimantIdentityAdapter;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

class AuthorizeWithdrawalClaimantStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::CASH_DOMAIN;
    }

    public static function description(): string
    {
        return 'Authorize that the resolved claimant is allowed to withdraw against the voucher (ownership, binding, or policy rules).';
    }

    public function __construct(
        protected CashClaimantAuthorizationContract $claimantAuthorization,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->contact === null) {
            throw new LogicException('Withdrawal claimant must be resolved before authorization.');
        }

        $this->claimantAuthorization->authorize(
            new VoucherWithdrawableInstrumentAdapter($context->voucher),
            new ContactClaimantIdentityAdapter($context->contact),
        );

        return $next($context);
    }
}
