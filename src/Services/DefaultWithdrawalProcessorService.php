<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LogicException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

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
    ) {}

    public function process(Voucher $voucher, array $payload): WithdrawPayCodeResultData
    {
        $context = $this->withdrawalPipeline->process(
            new WithdrawalPipelineContextData(
                voucher: $voucher,
                payload: $payload,
            ),
        );

        if ($context->result === null) {
            throw new LogicException('Withdrawal result was not built.');
        }

        return $context->result;
    }
}
