<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\EmiCore\Contracts\ProviderPayerMobileInterpreter;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;
use LogicException;

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

        return $this->fromObservation($recognition->canonicalObservation);
    }

    public function fromObservation(?ProviderFundingObservation $observation): ?string
    {
        if ($observation === null) {
            return null;
        }

        return $this->interpret($observation->provider_code, $observation->payer_institution_ciphertext, $observation->payer_account_ciphertext);
    }

    public function interpret(string $provider, ?string $institution, ?string $account): ?string
    {
        $interpreter = $this->interpreterFor($provider);
        $mobile = $interpreter?->resolve($institution, $account);

        return is_string($mobile) && preg_match('/^639[0-9]{9}$/', $mobile) === 1 ? $mobile : null;
    }

    public function supports(string $provider): bool
    {
        return $this->interpreterFor($provider) !== null;
    }

    private function interpreterFor(string $provider): ?ProviderPayerMobileInterpreter
    {
        $matches = [];
        foreach (app()->tagged('emi.provider-payer-mobile-interpreters') as $candidate) {
            if ($candidate instanceof ProviderPayerMobileInterpreter && strtolower($candidate->providerCode()) === strtolower($provider)) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) > 1) {
            throw new LogicException('Multiple provider payer mobile interpreters are registered.');
        }

        return $matches[0] ?? null;
    }
}
