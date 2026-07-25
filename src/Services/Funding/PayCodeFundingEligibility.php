<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Funding\PayCodeFundingEligibilityData;
use LBHurtado\XChange\Enums\PayCodeSettlementDestination;
use LBHurtado\XChange\Services\NamedVoucherSliceService;

final readonly class PayCodeFundingEligibility
{
    public function __construct(
        private NamedVoucherSliceService $namedSlices,
    ) {}

    public function evaluate(Voucher $voucher): PayCodeFundingEligibilityData
    {
        if (! $voucher->owner instanceof Model) {
            return $this->blocked(
                'issuer_unavailable',
                'The Pay Code issuer is unavailable.',
            );
        }

        if (! $voucher->canRedeem()) {
            return $this->blocked(
                'not_claimable',
                'This Pay Code is expired, redeemed, or otherwise unavailable.',
            );
        }

        if ($this->namedSlices->hasNamedSlices($voucher)) {
            return $this->blocked(
                'slices_not_supported',
                'Account Funding currently requires one whole Pay Code amount.',
            );
        }

        $destinations = collect((array) data_get(
            $voucher->metadata,
            'treasury.account_funding.destinations',
            [],
        ))
            ->map(static fn (mixed $destination): string => mb_strtolower(trim((string) $destination)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! in_array(PayCodeSettlementDestination::AccountFunding->value, $destinations, true)) {
            return $this->blocked(
                'payout_only',
                'This Pay Code is available only through its original claim path.',
            );
        }

        $accountFunding = data_get(
            $voucher->metadata,
            'treasury.account_funding',
        );

        if (
            ! is_array($accountFunding)
            || ($accountFunding['status'] ?? null) !== 'ready'
            || ($accountFunding['pricing_profile'] ?? null) !== 'account-funding-v1'
            || (int) ($accountFunding['provider_cost_minor'] ?? -1) !== 0
            || ($accountFunding['provider_calls'] ?? null) !== false
        ) {
            return $this->blocked(
                'commercial_profile_unavailable',
                'This Pay Code does not carry the approved Account Funding commercial profile.',
            );
        }

        $reservation = data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation',
        );
        $amountMinor = (int) round(
            (float) data_get($voucher->metadata, 'instructions.cash.amount', 0) * 100,
        );
        $currency = mb_strtoupper(trim((string) data_get(
            $voucher->metadata,
            'instructions.cash.currency',
            '',
        )));

        if (
            ! is_array($reservation)
            || ($reservation['status'] ?? null) !== 'reserved'
            || trim((string) ($reservation['connection_reference'] ?? '')) === ''
            || trim((string) ($reservation['operation_reference'] ?? '')) === ''
            || (int) ($reservation['amount_minor'] ?? 0) !== $amountMinor
            || mb_strtoupper(trim((string) ($reservation['currency'] ?? ''))) !== $currency
        ) {
            return $this->blocked(
                'reserve_unavailable',
                'The Pay Code does not have a matching Treasury reserve.',
            );
        }

        if ($amountMinor <= 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return $this->blocked(
                'amount_unavailable',
                'The Pay Code amount or currency is unavailable.',
            );
        }

        return new PayCodeFundingEligibilityData(
            eligible: true,
            status: 'eligible',
            message: 'This Pay Code can be added to Client Funds.',
            amountMinor: $amountMinor,
            currency: $currency,
            connectionReference: (string) $reservation['connection_reference'],
            reservationOperationReference: (string) $reservation['operation_reference'],
        );
    }

    private function blocked(
        string $status,
        string $message,
    ): PayCodeFundingEligibilityData {
        return new PayCodeFundingEligibilityData(
            eligible: false,
            status: $status,
            message: $message,
        );
    }
}
