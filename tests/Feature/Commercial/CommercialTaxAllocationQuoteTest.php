<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialChargeData;
use LBHurtado\XChange\Actions\Commercial\PostCommercialSale;
use LBHurtado\XChange\Data\Commercial\CommercialAllocationDispositionPlanData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Models\CommercialTaxProfile;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialQuoteService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XCommerce\Services\DeterministicCommercialSaleFactory;
use LBHurtado\XProvisioning\Enums\CommercialSettlementDisposition;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    config()->set(
        'x-change.commercial.component_economics.bootstrap.tax_policy_reference',
        'tax-profile:3neti:ph:v1',
    );
    config()->set('x-change.commercial.tax_profiles.profiles.tax-profile:3neti:ph:v1', [
        'version' => 1,
        'jurisdiction' => 'PH',
        'currency' => 'PHP',
        'tax_type' => 'withholding',
        'calculation_basis' => 'gross_external_allocation',
        'rate_basis_points' => 200,
        'rounding_method' => 'half_up_minor',
        'rounding_scope' => 'line_total',
        'collection_method' => 'deduct_from_recipient',
        'tax_recipient_reference' => 'tax-authority:ph:bir',
        'effective_from' => '2026-01-01T00:00:00+00:00',
        'effective_until' => null,
    ]);

    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:tax-allocation');
});

it('freezes deterministic net and tax payable lines into the authoritative quote', function (): void {
    $quote = app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:test:governed-tax-allocation',
    );
    $taxLines = collect($quote->allocationPlan->lines)->where('category', 'tax_payable');
    $netLines = collect($quote->allocationPlan->lines)
        ->where('destinationKind', CommercialAllocationDestinationKind::ExternalRecipient);

    expect(CommercialTaxProfile::query()->count())->toBe(1)
        ->and($quote->allocationPlan->totalAllocatedMinor())->toBe($quote->totalPriceMinor)
        ->and($quote->taxProfileSnapshots)->toHaveKey('tax-profile:3neti:ph:v1')
        ->and($taxLines)->not->toBeEmpty()
        ->and($netLines)->not->toBeEmpty()
        ->and($taxLines->every(
            static fn ($line): bool => $line->destinationKind === CommercialAllocationDestinationKind::TaxAuthority
                && $line->recipientReference === 'tax-authority:ph:bir'
                && $line->taxProfileVersion === 1
                && strlen((string) $line->taxProfileSnapshotHash) === 64
                && $line->grossAmountMinor === $line->amountMinor
                    + $netLines->firstWhere('parentPolicyRuleReference', $line->parentPolicyRuleReference)?->amountMinor,
        ))->toBeTrue()
        ->and($quote->toArray())->toHaveKey('tax_profile_snapshots');
});

it('fails before quote acceptance when persisted tax evidence is missing or changed', function (): void {
    DB::table('x_change_commercial_tax_profiles')->delete();

    expect(fn () => app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:test:missing-tax-evidence',
    ))->toThrow(CommercialSaleConflict::class, 'does not have exactly one effective governed version');
});

it('rejects tax evidence that diverges from its immutable snapshot', function (): void {
    DB::table('x_change_commercial_tax_profiles')->update([
        'snapshot_hash' => str_repeat('a', 64),
    ]);

    expect(fn () => app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:test:changed-tax-evidence',
    ))->toThrow(CommercialSaleConflict::class, 'evidence does not match');
});

it('posts net and Tax Payable as distinct idempotent accounting allocations', function (): void {
    $quote = app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:test:tax-posting',
    );
    $snapshot = (new DeterministicCommercialSaleFactory)->accept(
        quote: $quote,
        acceptanceEventReference: 'pay-code-issued:test:tax-posting',
        buyerReference: 'principal:account:tax-posting-test',
        acceptedAt: '2026-08-16T12:00:00+08:00',
    );
    $positionOperations = Mockery::mock(TreasuryPositionOperationContract::class);
    $positionOperations->shouldReceive('charge')
        ->once()
        ->andReturnUsing(static fn (TreasuryPositionCommercialChargeData $charge): TreasuryPositionCommercialChargeData => $charge);
    $positionOperations->shouldReceive('allocate')
        ->times(collect($quote->allocationPlan->lines)->where('amountMinor', '>', 0)->count())
        ->andReturnUsing(static fn (TreasuryPositionAllocationData $allocation): TreasuryPositionAllocationData => $allocation);
    app()->instance(TreasuryPositionOperationContract::class, $positionOperations);

    $destinations = collect($quote->allocationPlan->lines)->mapWithKeys(
        static fn ($line): array => [
            $line->policyRuleReference => $line->category === 'tax_payable'
                ? 'position:tax-payable'
                : 'position:commercial-recipient',
        ],
    )->all();
    $dispositions = collect($quote->allocationPlan->lines)
        ->where('destinationKind', CommercialAllocationDestinationKind::ExternalRecipient)
        ->mapWithKeys(static function ($line): array {
            $designation = CommercialRecipientDesignation::query()
                ->where('designation_reference', $line->designationReference)
                ->sole();

            return [$line->policyRuleReference => new CommercialAllocationDispositionPlanData(
                policyRuleReference: $line->policyRuleReference,
                disposition: CommercialSettlementDisposition::RetainPayable,
                designationReference: $designation->designation_reference,
                authorityReference: $designation->authority_reference,
                authorityHash: $designation->authority_hash,
            )];
        })
        ->all();
    $sale = app(PostCommercialSale::class)->execute(
        snapshot: $snapshot,
        sourceClientFundsPositionReference: 'position:client-funds',
        commercialClearingPositionReference: 'position:commercial-clearing',
        destinationPositionReferences: $destinations,
        dispositionPlans: $dispositions,
    );
    $taxAllocations = $sale->allocations->where('category', 'tax_payable');

    expect($sale->status)->toBe('posted')
        ->and($sale->allocations->sum('amount_minor'))->toBe($quote->totalPriceMinor)
        ->and($taxAllocations)->not->toBeEmpty()
        ->and($taxAllocations->every(
            static fn ($allocation): bool => $allocation->destination_position_reference === 'position:tax-payable'
                && data_get($allocation->metadata, 'parent_policy_rule_reference') !== null
                && data_get($allocation->metadata, 'gross_amount_minor') > 0
                && data_get($allocation->metadata, 'tax_profile_version') === 1
                && strlen((string) data_get($allocation->metadata, 'tax_profile_snapshot_hash')) === 64,
        ))->toBeTrue();
});
