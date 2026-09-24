<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final class CampaignWalletPayerMobile
{
    public function forCompletionVoucher(Voucher $voucher): ?string
    {
        if (data_get($voucher->getAttribute('metadata'), 'instructions.execution.driver') !== CampaignCoverageCompletionExecutionDriver::Key) {
            return null;
        }

        $issuance = CompletionPayCodeIssuance::query()
            ->with('coverage.recognition.canonicalObservation')
            ->where('voucher_id', $voucher->getKey())
            ->first();

        return $issuance === null ? null : $this->resolve($issuance->coverage->recognition);
    }

    public function resolve(CampaignPaymentRecognition $recognition): ?string
    {
        $recognition->loadMissing('canonicalObservation');
        $observation = $recognition->canonicalObservation;
        $institution = strtoupper(trim((string) $observation?->payer_institution_ciphertext));
        $account = trim((string) $observation?->payer_account_ciphertext);
        $mobile = MobileNumber::normalize($account);

        if (! in_array($institution, ['GXCHPHM2XXX', 'PAPHPHM1XXX', 'GCASH', 'MAYA', 'PAYMAYA'], true)
            || ! preg_match('/^(?:0|63|\+63)9[0-9]{9}$/', $account)
            || ! preg_match('/^639[0-9]{9}$/', (string) $mobile)) {
            return null;
        }

        return $mobile;
    }
}
