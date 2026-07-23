<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class FinalizeFundingSuspenseMonitoring
{
    public function handle(int $webhookReceiptId): int
    {
        return DB::transaction(function () use ($webhookReceiptId): int {
            $cases = FundingSuspenseCase::query()
                ->where('webhook_receipt_id', $webhookReceiptId)
                ->where('status', 'monitoring')
                ->with('fundingIntent')
                ->lockForUpdate()
                ->get();
            $resolved = 0;

            foreach ($cases as $case) {
                $resolutionCode = match ($case->fundingIntent?->status) {
                    FundingIntentStatus::Settled => 'verification_retry_settled',
                    FundingIntentStatus::AwaitingFunds => 'verification_retry_no_funds_observed',
                    FundingIntentStatus::Suspense => 'verification_retry_returned_to_suspense',
                    FundingIntentStatus::Reversed => 'verification_retry_reversed',
                    default => null,
                };

                if ($resolutionCode === null) {
                    continue;
                }

                $case->forceFill([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by_type' => 'funding_verification_runtime',
                    'resolved_by_id' => (string) $webhookReceiptId,
                    'resolution_code' => $resolutionCode,
                    'resolution' => [
                        'funding_intent_status' => $case->fundingIntent?->status->value,
                    ],
                ])->saveQuietly();
                $resolved++;
            }

            return $resolved;
        }, attempts: 3);
    }
}
