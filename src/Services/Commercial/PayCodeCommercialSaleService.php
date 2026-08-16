<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Commercial\PostCommercialSale;
use LBHurtado\XChange\Contracts\CommercialPartnerResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XCommerce\Data\CommercialAccountingContextData;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialAttributionSnapshotData;
use LBHurtado\XCommerce\Data\CommercialQuoteData;
use LBHurtado\XCommerce\Services\DeterministicCommercialSaleFactory;

class PayCodeCommercialSaleService
{
    public function __construct(
        private readonly PayCodeCommercialQuoteService $quotes,
        private readonly PostCommercialSale $posting,
        private readonly TreasuryProviderConnectionCatalog $connections,
        private readonly TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private readonly TreasuryProvisioningService $systemPortfolio,
        private readonly TreasuryPrincipalReferenceResolverContract $principalReferences,
        private readonly CommercialPartnerResolverContract $partners,
        private readonly CommercialPartnerPositionService $partnerPositions,
        private readonly CommercialPricingAcceptanceGuard $pricingAcceptance,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $issued
     * @return array<string, mixed>
     */
    public function post(
        Model $issuer,
        array $input,
        array $issued,
        string $provider,
        ?PricingEstimateData $acceptedEstimate = null,
    ): array {
        $voucherId = (int) ($issued['voucher_id'] ?? 0);
        $code = trim((string) ($issued['code'] ?? ''));

        if ($voucherId < 1 || $code === '') {
            throw new CommercialSaleConflict('Commercial posting requires an issued Pay Code identity.');
        }

        $instructions = VoucherInstructionsData::from($input);
        $currency = mb_strtoupper((string) ($issued['currency'] ?? data_get($input, 'cash.currency', 'PHP')));
        $connection = $this->connection($provider, $currency);
        $accountPortfolio = $this->accountPortfolios->provision($issuer, [$connection->reference]);
        $systemPortfolio = $this->systemPortfolio->provision([$connection->reference]);
        $buyerReference = $this->principalReferences->resolve($issuer);
        $attribution = $this->attribution($input, $voucherId);
        $quote = $this->quotes->quote(
            instructions: $instructions,
            sourceCommercialEventReference: 'pay-code-generation:voucher:'.$voucherId,
            attribution: $attribution,
        );
        if ($acceptedEstimate instanceof PricingEstimateData) {
            $this->pricingAcceptance->assertQuote($acceptedEstimate, $quote);
        }
        $snapshot = (new DeterministicCommercialSaleFactory)->accept(
            quote: $quote,
            acceptanceEventReference: 'pay-code-issued:voucher:'.$voucherId,
            buyerReference: $buyerReference,
            acceptedAt: (string) ($issued['issued_at'] ?? now()->toRfc3339String()),
            accountingContext: $this->accountingContext(
                input: $input,
                connection: $connection,
                attribution: $attribution,
                quote: $quote,
            ),
        );
        $source = $this->position(
            $accountPortfolio->positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $clearing = $this->position(
            $systemPortfolio->positions,
            TreasuryPositionPurpose::CommercialClearing,
        );
        $destinations = [];

        foreach ($quote->allocationPlan->lines as $line) {
            $positionPurpose = $this->destinationPurpose($line);

            if ($positionPurpose === TreasuryPositionPurpose::PartnerCommissionPayable) {
                $partnerReference = $line->recipientReference;
                $partner = $this->partners->resolve($partnerReference);

                if (! $partner instanceof Model) {
                    throw new CommercialSaleConflict(
                        "Commercial partner [{$partnerReference}] cannot be resolved.",
                    );
                }

                $destinations[$line->policyRuleReference] = $this->partnerPositions
                    ->provision($partner, $connection)
                    ->positionReference;

                continue;
            }

            $destinations[$line->policyRuleReference] = $this->position(
                $systemPortfolio->positions,
                $positionPurpose,
            )->positionReference;
        }

        $sale = $this->posting->execute(
            snapshot: $snapshot,
            sourceClientFundsPositionReference: $source->positionReference,
            commercialClearingPositionReference: $clearing->positionReference,
            destinationPositionReferences: $destinations,
        );

        return $this->result($sale, $code);
    }

    private function destinationPurpose(CommercialAllocationLineData $line): TreasuryPositionPurpose
    {
        return match ($line->category) {
            'provider_cost', 'provider_cost_payable' => TreasuryPositionPurpose::ProviderCostPayable,
            'service_provider_payable', 'royalty', 'royalty_payable' => TreasuryPositionPurpose::RoyaltyPayable,
            'partner_commission', 'partner_commission_payable' => TreasuryPositionPurpose::PartnerCommissionPayable,
            'tax', 'tax_payable' => TreasuryPositionPurpose::TaxPayable,
            'institution_owned_funds' => TreasuryPositionPurpose::InstitutionOwnedFunds,
            'product_revenue' => TreasuryPositionPurpose::ProductRevenue,
            'commercial_revenue' => TreasuryPositionPurpose::CommercialRevenue,
            default => throw new CommercialSaleConflict(
                "Commercial allocation category [{$line->category}] has no governed Treasury Position purpose.",
            ),
        };
    }

    private function connection(string $provider, string $currency): TreasuryProviderConnectionData
    {
        $matches = array_values(array_filter(
            $this->connections->active(),
            static fn ($connection): bool => $connection->provider === mb_strtolower(trim($provider))
                && $connection->currency === $currency,
        ));

        if (count($matches) !== 1) {
            throw new CommercialSaleConflict(
                'Commercial posting requires exactly one active Treasury connection for the provider and currency.',
            );
        }

        return $matches[0];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(array $positions, TreasuryPositionPurpose $purpose): TreasuryPositionData
    {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new CommercialSaleConflict(
                "Commercial Treasury Position [{$purpose->value}] is unavailable or ambiguous.",
            );
        }

        return $matches[0];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function attribution(array $input, int $voucherId): CommercialAttributionSnapshotData
    {
        $participants = collect((array) data_get($input, 'metadata.commercial_attribution', []))
            ->mapWithKeys(static function (mixed $reference, mixed $role): array {
                $role = trim((string) $role);
                $reference = trim((string) $reference);

                $role = match ($role) {
                    'partner' => 'sales_partner',
                    'originator' => 'originating_partner',
                    default => $role,
                };

                return $role !== '' && $reference !== ''
                    ? [$role => $reference]
                    : [];
            })
            ->all();

        return new CommercialAttributionSnapshotData(
            reference: 'attribution:pay-code:voucher:'.$voucherId,
            version: 1,
            participants: $participants,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function accountingContext(
        array $input,
        TreasuryProviderConnectionData $connection,
        CommercialAttributionSnapshotData $attribution,
        CommercialQuoteData $quote,
    ): CommercialAccountingContextData {
        $providerCostMinor = collect($quote->allocationPlan->lines)
            ->where('category', 'provider_cost')
            ->sum('amountMinor');
        $productReference = collect($quote->allocationPlan->lines)
            ->firstWhere('category', 'product_revenue')
            ?->recipientReference;
        $partnerReference = collect($quote->allocationPlan->lines)
            ->firstWhere('category', 'partner_commission')
            ?->recipientReference;

        return new CommercialAccountingContextData(
            schemaVersion: (int) config(
                'x-change.commercial.accounting.schema_version',
                2,
            ),
            provider: $connection->provider,
            connectionReference: $connection->reference,
            settlementRail: (string) data_get(
                $input,
                'cash.settlement_rail',
                'UNSPECIFIED',
            ),
            currency: $connection->currency,
            productReference: (string) ($productReference ?? 'product:pay-code'),
            recognitionPolicyReference: (string) config(
                'x-change.commercial.accounting.recognition_policy_reference',
                'recognition:pay-code-issuance:v1',
            ),
            expectedProviderCostMinor: (int) $providerCostMinor,
            partnerReference: (string) ($partnerReference ?? 'partner:none'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function result(CommercialSale $sale, string $code): array
    {
        return [
            'total_minor' => $sale->total_price_minor,
            'total' => $sale->total_price_minor / 100,
            'currency' => $sale->currency,
            'commercial_sale_reference' => $sale->reference,
            'commercial_status' => $sale->status,
            'allocations' => $sale->allocations
                ->map(static fn ($allocation): array => [
                    'sequence' => $allocation->sequence,
                    'rule_reference' => $allocation->policy_rule_reference,
                    'category' => $allocation->category,
                    'recipient_reference' => $allocation->recipient_reference,
                    'amount_minor' => $allocation->amount_minor,
                    'amount' => $allocation->amount_minor / 100,
                    'currency' => $allocation->currency,
                    'status' => $allocation->status,
                    'pay_code' => $code,
                ])
                ->all(),
            'debit' => [
                'id' => null,
                'amount' => (string) (-1 * $sale->total_price_minor),
            ],
        ];
    }
}
