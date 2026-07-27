<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Arr;

final class QuickGenerateTemplateBlueprintSanitizer
{
    /**
     * @param  array<string, mixed>  $instructions
     * @return array<string, mixed>
     */
    public function sanitize(
        array $instructions,
        bool $includeAmount = true,
        bool $includePurpose = true,
    ): array {
        data_set(
            $instructions,
            'metadata.custom.cockpit.template_preferences.mobile_validation',
            filled(data_get($instructions, 'cash.validation.mobile')),
        );
        data_set(
            $instructions,
            'metadata.custom.cockpit.template_preferences.payable_validation',
            filled(data_get($instructions, 'cash.validation.payable')),
        );

        Arr::forget($instructions, [
            'cash.validation.secret',
            'cash.validation.mobile',
            'validation.secret',
            'issuer_id',
            'metadata.issuer_id',
            'metadata.collection_wallet_id',
            'metadata.custom.cockpit.recipient_reference',
            'metadata.custom.cockpit.campaign_context',
            'metadata.custom.cockpit.saved_template',
            'metadata.campaign',
            'campaign',
            'feedback.email',
            'feedback.mobile',
            'feedback.webhook',
            'starts_at',
            'expires_at',
            'idempotency_key',
            'metadata.idempotency_key',
            'code',
            'voucher_code',
        ]);

        if (! $includeAmount) {
            Arr::forget($instructions, [
                'cash.amount',
                'target_amount',
            ]);
        }

        if (! $includePurpose) {
            Arr::forget($instructions, 'rider.message');
        }

        return $instructions;
    }
}
