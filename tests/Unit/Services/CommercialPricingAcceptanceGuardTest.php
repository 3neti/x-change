<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialRecipientDesignationResolverContract;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Exceptions\CommercialPricingChanged;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialComponentEconomicsFactory;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\CommercialPricingAcceptanceGuard;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialQuoteService;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

it('rejects an Offering activation that changes between estimation and sale posting', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $componentEconomics = app(BootstrapCommercialComponentEconomicsFactory::class)->make('pay_code', $offering);
    app()->bind(
        CommercialOfferingResolverContract::class,
        fn (): CommercialOfferingResolverContract => new class($offering) implements CommercialOfferingResolverContract
        {
            public function __construct(private readonly CommercialOfferingData $offering) {}

            public function resolve(string $profile): CommercialOfferingData
            {
                return $this->offering;
            }
        },
    );
    app()->bind(
        CommercialComponentEconomicsResolverContract::class,
        fn (): CommercialComponentEconomicsResolverContract => new class($componentEconomics) implements CommercialComponentEconomicsResolverContract
        {
            public function __construct(private readonly CommercialComponentEconomicsSetData $componentEconomics) {}

            public function resolve(string $profile): CommercialComponentEconomicsSetData
            {
                return $this->componentEconomics;
            }
        },
    );
    app()->bind(
        CommercialRecipientDesignationResolverContract::class,
        fn (): CommercialRecipientDesignationResolverContract => new class implements CommercialRecipientDesignationResolverContract
        {
            public function resolve(string $designationReference): CommercialRecipientDesignation
            {
                return new CommercialRecipientDesignation([
                    'designation_reference' => $designationReference,
                    'counterparty_reference' => 'counterparty:3neti',
                    'commercial_role' => 'service_aggregator',
                    'component_scope' => array_map(
                        static fn ($item): string => $item->reference,
                        app(BootstrapCommercialOfferingFactory::class)->make('pay_code')->catalog->items,
                    ),
                    'agreement_reference' => 'agreement:commissioning:institution-3neti:v1',
                ]);
            }
        },
    );
    $quote = app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:voucher:1',
    );
    $estimate = new PricingEstimateData(
        total: $quote->totalPriceMinor / 100,
        commercial_offering_reference: $offering->reference,
        commercial_offering_version: $offering->version,
        commercial_offering_snapshot_hash: $offering->snapshotHash(),
    );
    $guard = app(CommercialPricingAcceptanceGuard::class);

    $guard->assertQuote($estimate, $quote);

    $stale = clone $estimate;
    $stale->commercial_offering_version++;

    expect(fn () => $guard->assertQuote($stale, $quote))
        ->toThrow(CommercialPricingChanged::class, 'changed before issuance completed');
});

it('accepts the exact governed Offering identity and rejects a stale browser estimate', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $estimate = new PricingEstimateData(
        total: 18,
        commercial_offering_reference: $offering->reference,
        commercial_offering_version: $offering->version,
        commercial_offering_snapshot_hash: $offering->snapshotHash(),
    );
    $guard = app(CommercialPricingAcceptanceGuard::class);

    $guard->assertExpected([
        'offering_reference' => $offering->reference,
        'offering_version' => $offering->version,
        'offering_snapshot_hash' => $offering->snapshotHash(),
    ], $estimate);

    expect(fn () => $guard->assertExpected([
        'offering_reference' => $offering->reference,
        'offering_version' => $offering->version,
        'offering_snapshot_hash' => str_repeat('0', 64),
    ], $estimate))->toThrow(CommercialPricingChanged::class, 'no longer active');
});
