<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Exceptions\FundingEvidenceAlreadyClaimed;
use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\FundingIntent;

final readonly class ClaimFundingEvidence
{
    public function handle(
        FundingIntent $intent,
        ProviderFundingObservation $observation,
    ): FundingEvidenceClaim {
        try {
            return DB::transaction(function () use ($intent, $observation): FundingEvidenceClaim {
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
                    if ((int) $existing->funding_intent_id !== (int) $intent->getKey()) {
                        throw FundingEvidenceAlreadyClaimed::forAnotherIntent();
                    }

                    return $existing;
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

            if ($existing instanceof FundingEvidenceClaim
                && (int) $existing->funding_intent_id !== (int) $intent->getKey()) {
                throw FundingEvidenceAlreadyClaimed::forAnotherIntent();
            }

            throw $exception;
        }
    }
}
