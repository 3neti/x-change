<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

final readonly class FundingMethodSelectorCockpitReadModel
{
    /**
     * @param  array<string, mixed>  $fundingInstructions
     * @return array<string, mixed>
     */
    public function forOnDemandIssuance(
        string $orderReference,
        int $amountMinor,
        string $currency,
        string $status,
        ?string $expiresAt,
        array $fundingInstructions,
    ): array {
        $bankTransferEnabled = (bool) config(
            'x-change.funding.requests.bank_transfer.enabled',
            false,
        );
        $fixedQrPhAvailable = data_get($fundingInstructions, 'embedded_amount') === true
            && is_string(data_get($fundingInstructions, 'qr_code'));

        return [
            'schema' => 'x-change.cockpit.funding-method-selector.v1',
            'context' => 'pay_code_issuance',
            'intent_reference' => data_get($fundingInstructions, 'reference'),
            'order_reference' => $orderReference,
            'amount' => [
                'currency' => $currency,
                'principal_minor' => $amountMinor,
                'fee_minor' => 0,
                'required_minor' => $amountMinor,
                'principal' => number_format($amountMinor / 100, 2),
                'fee' => '0.00',
                'required' => number_format($amountMinor / 100, 2),
            ],
            'expires_at' => $expiresAt,
            'status' => $status,
            'default_mode' => 'bank_transfer',
            'notice' => 'Keep this window open. The Pay Code is issued only after the exact payment is verified.',
            'methods' => [
                [
                    'key' => 'bank_transfer',
                    'workspace_mode' => 'bank_transfer',
                    'label' => 'Bank Transfer',
                    'description' => 'Transfer the exact amount shown below, then ask x-change to check it.',
                    'available' => $bankTransferEnabled,
                    'selectable' => $bankTransferEnabled,
                    'unavailable_reason' => $bankTransferEnabled ? null : 'Bank transfer funding is not enabled.',
                ],
                [
                    'key' => 'qr_ph',
                    'workspace_mode' => 'self_top_up',
                    'label' => 'QR Ph',
                    'description' => 'Pay the exact amount encoded by the provider-generated QR.',
                    'available' => $fixedQrPhAvailable,
                    'selectable' => $fixedQrPhAvailable,
                    'unavailable_reason' => $fixedQrPhAvailable
                        ? null
                        : 'A fixed-amount QR Ph is not available for this order.',
                ],
                [
                    'key' => 'pay_code',
                    'workspace_mode' => 'pay_code',
                    'label' => 'Pay Code',
                    'description' => 'Funding with another Pay Code is not enabled yet.',
                    'available' => false,
                    'selectable' => false,
                    'unavailable_reason' => 'Pay Code funding will be enabled after recursion safeguards are proven.',
                ],
            ],
            'bank_transfer' => [
                'instructions' => $fundingInstructions,
                'reconciliation_reference' => [
                    'mode' => 'disabled',
                    'value' => null,
                    'label' => 'Transfer reference',
                    'instructions' => 'The exact amount, destination, currency, and observation window identify this payment.',
                ],
                'matching_strategies' => [
                    'reserved_exact_amount',
                    'destination_account',
                    'currency',
                    'observation_window',
                ],
            ],
            'qr_ph' => [
                'fixed_amount' => $fixedQrPhAvailable,
                'amount_minor' => $fixedQrPhAvailable ? $amountMinor : null,
                'currency' => $fixedQrPhAvailable ? $currency : null,
                'image' => $fixedQrPhAvailable ? data_get($fundingInstructions, 'qr_code') : null,
                'qr_mode' => $fixedQrPhAvailable ? data_get($fundingInstructions, 'qr_mode') : null,
                'transaction_type' => $fixedQrPhAvailable
                    ? data_get($fundingInstructions, 'transaction_type')
                    : null,
                'provider_generated' => $fixedQrPhAvailable
                    ? data_get($fundingInstructions, 'provider_generated') === true
                    : false,
                'notice' => $fixedQrPhAvailable
                    ? 'The exact amount is encoded in this order-specific QR. Do not edit the amount in the payment app.'
                    : null,
            ],
        ];
    }

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
