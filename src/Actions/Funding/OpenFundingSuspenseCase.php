<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class OpenFundingSuspenseCase
{
    /**
     * @param  array<string, bool|int|string|null>  $details
     */
    public function handle(
        string $provider,
        string $reasonCode,
        ?FundingIntent $intent = null,
        ?WebhookReceipt $receipt = null,
        ?ProviderFundingObservation $observation = null,
        array $details = [],
    ): FundingSuspenseCase {
        $provider = strtolower(trim($provider));
        $reasonCode = strtolower(trim($reasonCode));

        if ($provider === ''
            || $reasonCode === ''
            || ($intent === null && $receipt === null && $observation === null)) {
            throw new InvalidArgumentException('Funding suspense requires a provider, reason, and evidence reference.');
        }

        return DB::transaction(function () use (
            $provider,
            $reasonCode,
            $intent,
            $receipt,
            $observation,
            $details,
        ): FundingSuspenseCase {
            $lockedIntent = $intent === null
                ? null
                : FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            if ($lockedIntent !== null && ! in_array($lockedIntent->status, [
                FundingIntentStatus::Suspense,
                FundingIntentStatus::Verified,
                FundingIntentStatus::Settled,
                FundingIntentStatus::Reversed,
            ], true)) {
                throw new InvalidArgumentException(
                    'A Funding Intent must be suspended or post-settlement before opening its review case.',
                );
            }

            $caseKey = hash('sha256', implode('|', [
                $provider,
                $reasonCode,
                (string) ($lockedIntent?->getKey() ?? ''),
                (string) ($lockedIntent?->version ?? ''),
                (string) ($receipt?->getKey() ?? ''),
                (string) ($observation?->getKey() ?? ''),
            ]));

            return FundingSuspenseCase::query()->firstOrCreate(
                ['case_key' => $caseKey],
                [
                    'funding_intent_id' => $lockedIntent?->getKey(),
                    'provider_funding_observation_id' => $observation?->getKey(),
                    'webhook_receipt_id' => $receipt?->getKey(),
                    'provider_code' => $provider,
                    'reason_code' => $reasonCode,
                    'status' => 'open',
                    'details' => $details,
                    'opened_at' => now(),
                ],
            );
        }, attempts: 3);
    }
}
