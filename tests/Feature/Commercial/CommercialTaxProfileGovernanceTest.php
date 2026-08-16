<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Models\CommercialTaxProfile;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;
use LBHurtado\XChange\Services\Commercial\CommercialRecipientDesignationGuard;
use LBHurtado\XChange\Services\Commercial\CommercialTaxProfileRegistry;
use LBHurtado\XChange\Services\Commercial\PersistCommercialTaxProfile;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:tax-profile');
});

it('persists an immutable governed tax profile without tax identity evidence', function (): void {
    config()->set('x-change.commercial.tax_profiles.profiles.tax-profile:3neti:ph:v1', taxProfilePayload());

    $profile = app(CommercialTaxProfileRegistry::class)->resolve('tax-profile:3neti:ph:v1');
    $persisted = app(PersistCommercialTaxProfile::class)->execute($profile);
    $replay = app(PersistCommercialTaxProfile::class)->execute($profile);

    expect($replay->is($persisted))->toBeTrue()
        ->and(CommercialTaxProfile::query()->count())->toBe(1)
        ->and($persisted->snapshot)->not->toHaveKey('tin')
        ->and($persisted->snapshot_hash)->toHaveLength(64);

    config()->set(
        'x-change.commercial.tax_profiles.profiles.tax-profile:3neti:ph:v1.rate_basis_points',
        300,
    );

    expect(fn () => app(PersistCommercialTaxProfile::class)->execute(
        app(CommercialTaxProfileRegistry::class)->resolve('tax-profile:3neti:ph:v1'),
    ))->toThrow(CommercialSaleConflict::class, 'changed without a new version');
});

it('requires exact agreement between an allocation tax profile and recipient designation', function (): void {
    config()->set('x-change.commercial.tax_profiles.profiles.tax-profile:3neti:ph:v1', taxProfilePayload());
    $designation = CommercialRecipientDesignation::query()->firstOrFail();
    $plan = taxAllocationPlan($designation->designation_reference, 'tax-profile:3neti:ph:v1');

    expect(fn () => app(CommercialRecipientDesignationGuard::class)->assertPlan($plan))
        ->toThrow(DomainException::class, 'does not authorize tax profile');

    $designation->forceFill(['tax_profile_reference' => 'tax-profile:3neti:ph:v1'])->save();

    app(CommercialRecipientDesignationGuard::class)->assertPlan($plan);
    expect(true)->toBeTrue();
});

it('does not infer tax authority when both the allocation and designation omit it', function (): void {
    $designation = CommercialRecipientDesignation::query()->firstOrFail();

    app(CommercialRecipientDesignationGuard::class)->assertPlan(
        taxAllocationPlan($designation->designation_reference, null),
    );

    expect(CommercialTaxProfile::query()->count())->toBe(0);
});

it('fails closed when settlement disposition and Account binding disagree', function (): void {
    $designation = CommercialRecipientDesignation::query()->firstOrFail();
    $plan = taxAllocationPlan($designation->designation_reference, null);

    $designation->forceFill([
        'settlement_disposition' => 'internal_account_credit',
        'settlement_account_reference' => null,
    ])->save();

    expect(fn () => app(CommercialRecipientDesignationGuard::class)->assertPlan($plan))
        ->toThrow(DomainException::class, 'requires an internal settlement Account');

    $designation->forceFill([
        'settlement_disposition' => 'retain_payable',
        'settlement_account_reference' => 'account:must-not-be-used',
    ])->save();

    expect(fn () => app(CommercialRecipientDesignationGuard::class)->assertPlan($plan))
        ->toThrow(DomainException::class, 'cannot bind an Account while retaining its payable');
});

it('makes commissioning fail closed when only the designation claims tax authority', function (): void {
    CommercialRecipientDesignation::query()->firstOrFail()
        ->forceFill(['tax_profile_reference' => 'tax-profile:unilateral:v1'])
        ->save();

    $status = app(CommercialGovernanceInspector::class)->inspect();

    expect($status['operational'])->toBeFalse()
        ->and($status['issuance_available'])->toBeFalse()
        ->and($status['tax_profiles'])->toMatchArray([
            'operational' => false,
            'required_count' => 1,
            'ready_count' => 0,
        ])
        ->and(data_get($status, 'tax_profiles.profiles.0.message'))
        ->toBe('Allocation and recipient designation tax profiles must match exactly.');
});

/** @return array<string, int|string|null> */
function taxProfilePayload(): array
{
    return [
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
    ];
}

function taxAllocationPlan(string $designationReference, ?string $taxProfileReference): CommercialAllocationPlanData
{
    return new CommercialAllocationPlanData(
        sourceCommercialEventReference: 'event:tax-profile-test',
        policyReference: 'economics:tax-profile-test',
        policyVersion: 1,
        currency: 'PHP',
        allocationBaseMinor: 100,
        lines: [new CommercialAllocationLineData(
            policyRuleReference: 'inputs.fields.selfie::3neti-default-share',
            sequence: 1,
            lineType: CommercialWaterfallLineType::Allocation,
            category: 'service_provider_payable',
            recipientReference: 'counterparty:3neti',
            amountMinor: 100,
            currency: 'PHP',
            componentReference: 'inputs.fields.selfie',
            componentScheduleReference: 'component-allocation:pay-code:inputs.fields.selfie',
            componentScheduleVersion: 1,
            componentRuleReference: '3neti-default-share',
            componentRuleLineType: CommercialWaterfallLineType::Allocation,
            destinationKind: CommercialAllocationDestinationKind::ExternalRecipient,
            participantRole: 'service_aggregator',
            agreementReference: 'agreement:commissioning:institution-3neti:v1',
            designationReference: $designationReference,
            taxPolicyReference: $taxProfileReference,
            unitAmountMinor: 100,
            quantity: 1,
        )],
    );
}
