<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final class CampaignWalletPayerMobile
{
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
