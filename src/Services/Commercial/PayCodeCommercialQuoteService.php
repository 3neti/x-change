<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use BackedEnum;
use JsonException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;
use LBHurtado\XCommerce\Data\CommercialAttributionSnapshotData;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialQuoteData;
use LBHurtado\XCommerce\Data\CommercialQuoteLineInputData;
use LBHurtado\XCommerce\Services\DeterministicCommercialQuoteBuilder;

final class PayCodeCommercialQuoteService
{
    public function __construct(
        private readonly VoucherClaimPolicyResolver $claimPolicies,
        private readonly CommercialOfferingResolverContract $offerings,
        private readonly CommercialComponentEconomicsResolverContract $componentEconomics,
        private readonly DeterministicCommercialQuoteBuilder $quotes,
        private readonly CommercialRecipientDesignationGuard $recipientDesignations,
    ) {}

    /**
     * @throws JsonException
     */
    public function quote(
        VoucherInstructionsData $instructions,
        string $sourceCommercialEventReference,
        ?CommercialAttributionSnapshotData $attribution = null,
    ): CommercialQuoteData {
        $profile = $this->usesAccountFundingProfile($instructions) ? 'account_funding' : 'pay_code';
        $offering = $this->offerings->resolve($profile);
        $componentEconomics = $this->componentEconomics->resolve($profile);
        $catalog = $offering->catalog;
        $policy = $offering->waterfallPolicy;

        $quote = $this->quotes->build(
            sourceCommercialEventReference: $sourceCommercialEventReference,
            catalog: $catalog,
            waterfallPolicy: $policy,
            attribution: $attribution ?? new CommercialAttributionSnapshotData(
                reference: 'attribution:direct',
                version: 1,
            ),
            lineInputs: $this->lineInputs($instructions, $catalog),
            offering: $offering,
            componentEconomics: $componentEconomics,
        );
        $this->recipientDesignations->assertPlan($quote->allocationPlan);

        return $quote;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function estimate(VoucherInstructionsData $instructions): array
    {
        $sourceReference = 'pay-code-pricing-projection:'.hash(
            'sha256',
            json_encode(
                $instructions->toArray(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
        );
        $quote = $this->quote($instructions, $sourceReference);
        $componentsMinor = [];
        $charges = [];

        foreach ($quote->lines as $line) {
            $componentsMinor[$line->category] = ($componentsMinor[$line->category] ?? 0)
                + $line->totalPriceMinor;
            $charges[] = [
                'index' => $this->compatibilityChargeIndex($line->catalogItemReference),
                'catalog_item_reference' => $line->catalogItemReference,
                'label' => $line->label,
                'type' => $line->category,
                'quantity' => $line->quantity,
                'unit_price_minor' => $line->unitPriceMinor,
                'unit_price' => $line->unitPriceMinor / 100,
                'price_minor' => $line->totalPriceMinor,
                'price' => $line->totalPriceMinor / 100,
                'currency' => $line->currency,
                'commercial_quote_reference' => $quote->reference,
            ];
        }

        return [
            'currency' => $quote->currency,
            'base_fee_minor' => 0,
            'base_fee' => 0.0,
            'components_minor' => $componentsMinor,
            'components' => collect($componentsMinor)
                ->map(static fn (int $minor): float => $minor / 100)
                ->all(),
            'total_minor' => $quote->totalPriceMinor,
            'total' => $quote->totalPriceMinor / 100,
            'charges' => $charges,
            'commercial_quote_reference' => $quote->reference,
            'commercial_offering_reference' => $quote->offeringSnapshot?->reference,
            'commercial_offering_version' => $quote->offeringSnapshot?->version,
            'commercial_offering_snapshot_hash' => $quote->offeringSnapshot?->snapshotHash(),
            'catalog_reference' => $quote->catalogSnapshot->reference,
            'catalog_version' => $quote->catalogSnapshot->version,
            'waterfall_policy_reference' => $quote->waterfallPolicySnapshot->reference,
            'waterfall_policy_version' => $quote->waterfallPolicySnapshot->version,
            'component_economics_reference' => $quote->allocationPlan->policyReference,
            'component_economics_version' => $quote->allocationPlan->policyVersion,
            'component_economics_snapshot_hash' => $quote->componentEconomicsSnapshot?->snapshotHash(),
        ];
    }

    private function compatibilityChargeIndex(string $catalogItemReference): string
    {
        return match ($catalogItemReference) {
            'flow_type.collectible',
            'voucher_type.payable',
            'voucher_type.settlement',
            'inputs.fields.reference_code',
            'cash.validation.secret',
            'cash.validation.mobile',
            'cash.validation.payable',
            'rider.message',
            'rider.splash',
            'rider.url' => 'cash.amount',
            default => $catalogItemReference,
        };
    }

    private function usesAccountFundingProfile(
        VoucherInstructionsData $instructions,
    ): bool {
        $policy = $this->claimPolicies->resolveInstructions($instructions);
        $outcomes = collect($policy->outcomes);
        $usesAccountFunding = $policy->permits('account_funding');

        if ($usesAccountFunding && $outcomes->count() !== 1) {
            throw new PayCodeIssuanceFailed(
                'Dual-outcome Pay Codes remain disabled until execution-cost reserves are active.',
            );
        }

        if (! $usesAccountFunding) {
            return false;
        }

        if ($outcomes->sole()->pricingProfile !== 'account-funding-v1') {
            throw new PayCodeIssuanceFailed(
                'Account Funding Pay Codes require the account-funding-v1 pricing profile.',
            );
        }

        return true;
    }

    /**
     * @return list<CommercialQuoteLineInputData>
     */
    private function lineInputs(
        VoucherInstructionsData $instructions,
        CommercialCatalogData $catalog,
    ): array {
        $selected = [];
        $count = max(1, (int) ($instructions->count ?? 1));

        if ((float) ($instructions->cash?->amount ?? 0) > 0) {
            $selected[] = 'cash.amount';
        }

        $flowType = mb_strtolower(trim((string) data_get($instructions, 'metadata.flow_type', '')));

        if ($flowType === 'collectible') {
            $selected[] = 'flow_type.collectible';
        }

        $voucherType = data_get($instructions, 'voucher_type', '');

        if ($voucherType instanceof BackedEnum) {
            $voucherType = $voucherType->value;
        }

        $voucherType = mb_strtolower(trim((string) $voucherType));

        if ($voucherType !== '') {
            $selected[] = 'voucher_type.'.$voucherType;
        }

        if ($instructions->onboarding) {
            $selected[] = 'onboarding.enabled';
        }

        $fields = $this->fieldNames($instructions);

        foreach ($fields as $field) {
            $selected[] = 'inputs.fields.'.$field;
        }

        if (in_array('otp', $fields, true)
            || data_get($instructions, 'cash.validation.payable') === 'otp'
            || filled(data_get($instructions, 'cash.validation.otp'))) {
            $selected[] = 'inputs.fields.otp';
        }

        foreach (['email', 'mobile', 'webhook'] as $channel) {
            if (filled(data_get($instructions, "feedback.{$channel}"))) {
                $selected[] = "feedback.{$channel}";
            }
        }

        foreach (['secret', 'mobile'] as $validation) {
            if (filled(data_get($instructions, "cash.validation.{$validation}"))) {
                $selected[] = "cash.validation.{$validation}";
            }
        }

        if (filled(data_get($instructions, 'cash.validation.payable'))
            && data_get($instructions, 'cash.validation.payable') !== 'otp') {
            $selected[] = 'cash.validation.payable';
        }

        if (filled(data_get($instructions, 'validation.time'))) {
            $selected[] = 'validation.time';
        }

        if (filled(data_get($instructions, 'validation.location'))) {
            $selected[] = 'validation.location';
        }

        foreach (['message', 'splash', 'url'] as $rider) {
            if (filled(data_get($instructions, "rider.{$rider}"))) {
                $selected[] = "rider.{$rider}";
            }
        }

        $available = collect($catalog->items)
            ->reject(static fn ($item): bool => $item->deprecated)
            ->pluck('reference')
            ->all();

        if ($instructions->onboarding && ! in_array('onboarding.enabled', $available, true)) {
            throw new PayCodeIssuanceFailed(
                'The active commercial catalog does not price Account Onboarding.',
            );
        }

        return collect($selected)
            ->unique()
            ->filter(static fn (string $reference): bool => in_array($reference, $available, true))
            ->map(static fn (string $reference): CommercialQuoteLineInputData => new CommercialQuoteLineInputData(
                catalogItemReference: $reference,
                quantity: $count,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function fieldNames(VoucherInstructionsData $instructions): array
    {
        return collect((array) data_get($instructions, 'inputs.fields', []))
            ->map(static function (mixed $field): ?string {
                if (is_string($field)) {
                    return mb_strtolower(trim($field));
                }

                foreach (['value', 'name', 'key', 'type', 'field'] as $candidate) {
                    $value = data_get($field, $candidate);

                    if (is_string($value) && trim($value) !== '') {
                        return mb_strtolower(trim($value));
                    }
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
