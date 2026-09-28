<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

final readonly class FundingMethodSelectorCockpitReadModel
{
    /**
     * @param  array<string, mixed>  $fundingRequests
     * @param  array<string, mixed>  $standingFundingAddress
     * @return array<string, mixed>
     */
    public function forAccountFunding(
        array $fundingRequests,
        array $standingFundingAddress,
    ): array {
        $bankTransferEnabled = (bool) data_get(
            $fundingRequests,
            'bank_transfer.enabled',
            false,
        );
        $reservedExactAmountsEnabled = (bool) data_get(
            $fundingRequests,
            'bank_transfer.reserved_exact_amounts_enabled',
            false,
        );
        $qrPhAvailable = (bool) data_get(
            $standingFundingAddress,
            'available',
            false,
        );

        return [
            'schema' => 'x-change.cockpit.funding-method-selector.v1',
            'context' => 'account_funding',
            'intent_reference' => null,
            'amount' => null,
            'expires_at' => null,
            'status' => 'ready',
            'notice' => 'Funds appear in your Account only after confirmation from the bank or payment provider.',
            'methods' => [
                [
                    'key' => 'qr_ph',
                    'workspace_mode' => 'self_top_up',
                    'label' => 'QR Ph',
                    'description' => 'Scan your reusable QR Ph code, then check NetBank for confirmed funds.',
                    'available' => $qrPhAvailable,
                    'selectable' => true,
                    'unavailable_reason' => $qrPhAvailable
                        ? null
                        : 'QR Ph is not configured for this Account.',
                ],
                [
                    'key' => 'bank_transfer',
                    'workspace_mode' => 'bank_transfer',
                    'label' => 'Bank Transfer',
                    'description' => 'Reserve an exact amount and transfer it to the configured bank account.',
                    'available' => $bankTransferEnabled,
                    'selectable' => true,
                    'unavailable_reason' => $bankTransferEnabled
                        ? null
                        : 'Bank transfer funding is not enabled.',
                ],
                [
                    'key' => 'pay_code',
                    'workspace_mode' => 'pay_code',
                    'label' => 'Pay Code',
                    'description' => 'Add funds with a one-time Pay Code without a provider payout.',
                    'available' => true,
                    'selectable' => true,
                    'unavailable_reason' => null,
                ],
            ],
            'bank_transfer' => [
                'reconciliation_reference' => [
                    'mode' => 'disabled',
                    'value' => null,
                    'label' => 'Transfer reference',
                    'instructions' => 'No sender-entered reference is used as authoritative matching evidence.',
                ],
                'matching_strategies' => $reservedExactAmountsEnabled
                    ? [
                        'reserved_exact_amount',
                        'destination_account',
                        'currency',
                        'observation_window',
                    ]
                    : [
                        'destination_account',
                        'currency',
                        'observation_window',
                        'manual_review',
                    ],
            ],
        ];
    }
}
