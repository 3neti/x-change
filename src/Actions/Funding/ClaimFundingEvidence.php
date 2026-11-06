<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Exceptions\FundingEvidenceAlreadyClaimed;
use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingEvidenceClaimOwnership;
use LBHurtado\XChange\Services\Payment\ProviderEvidenceLock;

final readonly class ClaimFundingEvidence
{
    public function __construct(
        private FundingEvidenceClaimOwnership $ownership,
        private ProviderEvidenceLock $evidenceLock,
    ) {}

    public function handle(
        FundingIntent $intent,
        ProviderFundingObservation $observation,
    ): FundingEvidenceClaim {
        try {
            return DB::transaction(function () use ($intent, $observation): FundingEvidenceClaim {
                $this->evidenceLock->acquire($observation->provider_code, $observation->provider_transaction_id);
                if (VoucherCollection::query()->where('provider', $observation->provider_code)
                    ->where('provider_transaction_id', $observation->provider_transaction_id)->exists()) {
                    throw FundingEvidenceAlreadyClaimed::forAnotherIntent();
                }
                $ownedClaim = $this->ownership->effectiveClaimForIntent(
                    (int) $intent->getKey(),
                    lock: true,
                );
                $transactionHash = hash('sha256', implode('|', [
                    $observation->provider_code,
                    $observation->provider_transaction_id,
                ]));
                $existing = FundingEvidenceClaim::query()
                    ->where('provider_code', $observation->provider_code)
                    ->where('provider_transaction_hash', $transactionHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof FundingEvidenceClaim) {
                    if ($this->ownership->effectiveIntentId($existing, lock: true)
                        !== (int) $intent->getKey()) {
                        throw FundingEvidenceAlreadyClaimed::forAnotherIntent();
                    }

                    return $existing;
                }

                if ($ownedClaim instanceof FundingEvidenceClaim) {
                    throw FundingEvidenceAlreadyClaimed::forAnotherIntent();
                }

                return FundingEvidenceClaim::query()->create([
                    'funding_intent_id' => $intent->getKey(),
                    'provider_code' => $observation->provider_code,
                    'provider_transaction_hash' => $transactionHash,
                    'provider_funding_observation_id' => $observation->getKey(),
                    'claimed_at' => now(),
                ]);
            }, attempts: 5);
        } catch (QueryException $exception) {
            $existing = FundingEvidenceClaim::query()
                ->where('provider_code', $observation->provider_code)
                ->where('provider_transaction_hash', hash('sha256', implode('|', [
                    $observation->provider_code,
                    $observation->provider_transaction_id,
                ])))
                ->first();

            if ($existing instanceof FundingEvidenceClaim) {
                if ($this->ownership->effectiveIntentId($existing) !== (int) $intent->getKey()) {
                    throw FundingEvidenceAlreadyClaimed::forAnotherIntent();
                }

                return $existing;
            }

            throw $exception;
        }
    }
}
