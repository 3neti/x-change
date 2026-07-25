<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Data\ClaimOutcomeInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\VoucherClaimOutcomeData;
use LBHurtado\XChange\Data\Claim\VoucherClaimPolicyData;

final readonly class VoucherClaimPolicyResolver
{
    public function resolve(Voucher $voucher): VoucherClaimPolicyData
    {
        $instruction = $voucher->instructions->claim;

        if ($instruction !== null) {
            return $this->typedPolicy($voucher->instructions);
        }

        return $this->legacyPolicy($this->legacyVoucherOutcomeKeys($voucher));
    }

    public function resolveInstructions(
        VoucherInstructionsData $instructions,
    ): VoucherClaimPolicyData {
        if ($instructions->claim !== null) {
            return $this->typedPolicy($instructions);
        }

        return $this->legacyPolicy(
            $this->legacyIssuanceOutcomeKeys($instructions),
        );
    }

    private function typedPolicy(
        VoucherInstructionsData $instructions,
    ): VoucherClaimPolicyData {
        $instruction = $instructions->claim;
        $outcomes = array_map(
            static fn (ClaimOutcomeInstructionData $outcome): VoucherClaimOutcomeData => new VoucherClaimOutcomeData(
                key: $outcome->key,
                pricingProfile: $outcome->pricing_profile,
                requirements: $outcome->requirements ?? [],
            ),
            $instruction->outcomes,
        );

        return new VoucherClaimPolicyData(
            profile: $instruction->profile,
            outcomes: $outcomes,
            selection: $instruction->selection,
            consumption: $instruction->consumption,
            defaultOutcome: $instruction->default_outcome,
            onboarding: $instruction->onboarding?->toArray(),
            claimantBinding: $instruction->claimant?->toArray(),
            legacy: false,
        );
    }

    /**
     * @param  non-empty-list<string>  $outcomeKeys
     */
    private function legacyPolicy(array $outcomeKeys): VoucherClaimPolicyData
    {
        $outcomes = array_map(
            static fn (string $key): VoucherClaimOutcomeData => new VoucherClaimOutcomeData(
                key: $key,
                pricingProfile: $key === 'account_funding'
                    ? 'account-funding-v1'
                    : null,
            ),
            $outcomeKeys,
        );

        return new VoucherClaimPolicyData(
            profile: 'voucher.claim.legacy-v1',
            outcomes: $outcomes,
            selection: count($outcomes) > 1 ? 'claimant' : 'server',
            consumption: 'one_of',
            defaultOutcome: $outcomeKeys[0],
            onboarding: null,
            claimantBinding: null,
            legacy: true,
        );
    }

    /**
     * @return non-empty-list<string>
     */
    private function legacyVoucherOutcomeKeys(Voucher $voucher): array
    {
        $type = $voucher->voucher_type ?? $voucher->instructions->voucher_type;

        if ($type === VoucherType::PAYABLE) {
            return ['payment'];
        }

        if ($type === VoucherType::SETTLEMENT) {
            return ['settlement'];
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

        $outcomes = collect(['provider_disbursement', 'account_funding'])
            ->filter(static fn (string $outcome): bool => $outcome === 'provider_disbursement'
                || in_array($outcome, $destinations, true))
            ->values()
            ->all();

        return $outcomes === [] ? ['provider_disbursement'] : $outcomes;
    }

    /**
     * @return non-empty-list<string>
     */
    private function legacyIssuanceOutcomeKeys(
        VoucherInstructionsData $instructions,
    ): array {
        if ($instructions->voucher_type === VoucherType::PAYABLE) {
            return ['payment'];
        }

        if ($instructions->voucher_type === VoucherType::SETTLEMENT) {
            return ['settlement'];
        }

        $destinations = collect((array) data_get(
            $instructions,
            'metadata.custom.settlement.destinations',
            [],
        ))
            ->map(static fn (mixed $destination): string => match (mb_strtolower(trim((string) $destination))) {
                'provider_payout' => 'provider_disbursement',
                default => mb_strtolower(trim((string) $destination)),
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $destinations === [] ? ['provider_disbursement'] : $destinations;
    }
}
