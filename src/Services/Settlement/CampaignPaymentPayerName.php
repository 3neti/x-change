<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;

final class CampaignPaymentPayerName
{
    public function __construct(private readonly CampaignWalletPayerMobile $payerMobiles) {}

    public function forCompletionVoucher(Voucher $voucher): ?string
    {
        if (data_get($voucher->getAttribute('metadata'), 'instructions.execution.driver') !== CampaignCoverageCompletionExecutionDriver::Key) {
            return null;
        }

        $issuance = CompletionPayCodeIssuance::query()
            ->with('coverage.recognition.canonicalObservation')
            ->where('voucher_id', $voucher->getKey())
            ->first();

        if ($issuance === null || $this->payerMobiles->resolve($issuance->coverage->recognition) === null) {
            return null;
        }

        $name = trim((string) $issuance->coverage->recognition->canonicalObservation?->payer_name_ciphertext);

        if ($name === '' || mb_strlen($name) > 150 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return null;
        }

        return $name;
    }
}
