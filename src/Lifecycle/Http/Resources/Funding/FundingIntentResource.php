<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Funding;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;

class FundingIntentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var FundingIntent $intent */
        $intent = $this->resource;

        return [
            'success' => true,
            'data' => [
                'funding_intent' => [
                    'reference' => $intent->reference,
                    'account_reference' => $intent->account_reference,
                    'provider' => $intent->provider_code,
                    'expected_amount_minor' => $intent->expected_amount_minor,
                    'currency' => $intent->currency,
                    'status' => $intent->status->value,
                    'version' => $intent->version,
                    'expires_at' => $intent->expires_at?->toIso8601String(),
                    'next_step' => $intent->status === FundingIntentStatus::AwaitingFunds
                        ? 'transfer_exact_amount_to_provider'
                        : 'create_provider_instructions',
                    'funding_instructions' => $intent->status === FundingIntentStatus::AwaitingFunds
                        ? $intent->instructions_ciphertext
                        : null,
                ],
            ],
            'meta' => [
                'balance_changed' => false,
            ],
        ];
    }
}
