<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Cash\Exceptions\WithdrawalApprovalRequired;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use RuntimeException;

/**
 * Thin withdrawal processor adapter.
 *
 * The actual withdrawal workflow is delegated to WithdrawalPipeline.
 * Keep this class as the stable implementation of WithdrawalProcessorContract
 * while the pipeline owns claimant resolution, eligibility, authorization,
 * amount resolution, bank resolution, payout construction, rail guarding,
 * disbursement execution, wallet settlement, and result shaping.
 */
class DefaultWithdrawalProcessorService implements WithdrawalProcessorContract
{
    public function __construct(
        protected WithdrawalPipeline $withdrawalPipeline,
        protected WithdrawalResultFactory $withdrawalResultFactory,
    ) {}

    public function process(Voucher $voucher, array $payload): WithdrawPayCodeResultData
    {
        $context = new WithdrawalPipelineContextData(
            voucher: $voucher,
            payload: $payload,
        );

        try {
            $context = $this->withdrawalPipeline->process($context);
        } catch (WithdrawalApprovalRequired $e) {
            return $this->withdrawalResultFactory->approvalRequired($context, $e);
        }

        if (! $context->result instanceof WithdrawPayCodeResultData) {
            throw new RuntimeException('Withdrawal pipeline did not build a withdrawal result.');
        }

        return $context->result;
    }
}
