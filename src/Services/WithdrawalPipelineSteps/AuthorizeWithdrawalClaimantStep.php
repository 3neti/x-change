<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LogicException;
use LBHurtado\Cash\Contracts\CashClaimantAuthorizationContract;
use LBHurtado\XChange\Adapters\ContactClaimantIdentityAdapter;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

class AuthorizeWithdrawalClaimantStep
{
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
