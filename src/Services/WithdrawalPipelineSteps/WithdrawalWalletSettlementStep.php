<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalWalletSettlementService;

class WithdrawalWalletSettlementStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::SETTLEMENT;
    }

    public static function description(): string
    {
        return 'Apply wallet-level settlement by debiting the voucher wallet and recording financial transfers for the withdrawal.';
    }

    public function __construct(
        protected WithdrawalWalletSettlementService $walletSettlementService,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->payoutRequest === null) {
            throw new LogicException('Withdrawal payout request must be built before wallet settlement.');
        }

        if ($context->withdrawAmount === null) {
            throw new LogicException('Withdrawal amount must be resolved before wallet settlement.');
        }

        if ($context->sliceNumber === null) {
            throw new LogicException('Withdrawal slice number must be resolved before wallet settlement.');
        }

        $settlement = DB::transaction(function () use ($context) {
            $context->voucher->refresh();

            $settlement = $this->walletSettlementService->settle(
                voucher: $context->voucher,
                input: $context->payoutRequest,
                withdrawAmount: $context->withdrawAmount,
                sliceNumber: $context->sliceNumber,
            );

            $context->voucher->refresh();

            return $settlement;
        });

        $context->withSettlement($settlement);

        return $next($context);
    }
}
