<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialRecipientDesignationResolverContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialComponentEconomicsFactory;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\InstructionBackedPricingService;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');

    app()->bind(
        CommercialOfferingResolverContract::class,
        fn ($app): CommercialOfferingResolverContract => new class($app->make(BootstrapCommercialOfferingFactory::class)) implements CommercialOfferingResolverContract
        {
            public function __construct(
                private readonly BootstrapCommercialOfferingFactory $offerings,
            ) {}

            public function resolve(string $profile): CommercialOfferingData
            {
                return $this->offerings->make($profile);
            }
        },
    );
    app()->bind(
        CommercialComponentEconomicsResolverContract::class,
        fn ($app): CommercialComponentEconomicsResolverContract => new class($app->make(BootstrapCommercialOfferingFactory::class), $app->make(BootstrapCommercialComponentEconomicsFactory::class)) implements CommercialComponentEconomicsResolverContract
        {
            public function __construct(
                private readonly BootstrapCommercialOfferingFactory $offerings,
                private readonly BootstrapCommercialComponentEconomicsFactory $economics,
            ) {}

            public function resolve(string $profile): CommercialComponentEconomicsSetData
            {
                return $this->economics->make($profile, $this->offerings->make($profile));
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
                    'settlement_disposition' => 'retain_payable',
                ]);
            }
        },
    );
});

it('uses the immutable x-commerce catalog for Pay Code pricing and projections', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'secret',
                'mobile' => null,
                'payable' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['selfie', 'signature'],
        ],
        'feedback' => [
            'email' => 'recipient@example.test',
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Thank you',
            'url' => null,
            'splash' => null,
        ],
        'count' => 1,
    ]);

    $service = app(PricingServiceContract::class);
    $first = $service->estimate($instructions);
    $second = $service->estimate($instructions);

    expect($service)->toBeInstanceOf(InstructionBackedPricingService::class)
        ->and($first)->toBe($second)
        ->and($first['currency'])->toBe('PHP')
        ->and($first['total_minor'])->toBe(2_350)
        ->and($first['total'])->toBe(23.5)
        ->and(collect($first['charges'])->pluck('catalog_item_reference')->all())->toBe([
            'cash.amount',
            'inputs.fields.selfie',
            'inputs.fields.signature',
            'feedback.email',
            'cash.validation.secret',
            'rider.message',
        ])
        ->and($first['catalog_reference'])->toBe('pay-code')
        ->and($first['catalog_version'])->toBe(3)
        ->and($first['commercial_offering_reference'])->toBe('commercial-offering:pay_code')
        ->and($first['commercial_offering_version'])->toBe(1)
        ->and($first['commercial_offering_snapshot_hash'])->toHaveLength(64)
        ->and($first['waterfall_policy_reference'])->toBe('pay-code-commercial-waterfall')
        ->and($first['waterfall_policy_version'])->toBe(1)
        ->and($first['commercial_quote_reference'])->toStartWith('commercial-quote:');
});

it('prices KYC only when it is explicitly selected in the Voucher Instructions', function () {
    $selfieOnly = validVoucherInstructions(100, 'INSTAPAY', [
        'inputs' => ['fields' => ['selfie']],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);
    $selfieAndKyc = validVoucherInstructions(100, 'INSTAPAY', [
        'inputs' => ['fields' => ['selfie', 'kyc']],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $selfieOnlyCharges = collect(app(PricingServiceContract::class)->estimate($selfieOnly)['charges'])
        ->pluck('catalog_item_reference');
    $selfieAndKycCharges = collect(app(PricingServiceContract::class)->estimate($selfieAndKyc)['charges'])
        ->pluck('catalog_item_reference');

    expect($selfieOnlyCharges)
        ->toContain('inputs.fields.selfie')
        ->not->toContain('inputs.fields.kyc')
        ->and($selfieAndKycCharges)
        ->toContain('inputs.fields.selfie', 'inputs.fields.kyc');
});

it('prices onboarding as an explicit versioned commercial instruction', function () {
    $instructions = validVoucherInstructions(100, 'INSTAPAY', [
        'onboarding' => true,
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    $charges = collect($estimate['charges'])->keyBy('catalog_item_reference');

    expect($estimate['catalog_version'])->toBe(3)
        ->and($estimate['total_minor'])->toBe(2_500)
        ->and($estimate['charges'])->toHaveCount(2)
        ->and($charges->get('onboarding.enabled')['label'])->toBe('Account Onboarding')
        ->and($charges->get('onboarding.enabled')['price_minor'])->toBe(1_000);
});

it('fails closed when an onboarding instruction is missing from the active catalog', function () {
    $catalog = config('x-commerce.catalogs.pay_code');
    unset($catalog['items']['onboarding.enabled']);
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    $instructions = validVoucherInstructions(100, 'INSTAPAY', [
        'onboarding' => true,
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    expect(fn () => app(PricingServiceContract::class)->estimate($instructions))
        ->toThrow(
            PayCodeIssuanceFailed::class,
            'The active commercial catalog does not price Account Onboarding.',
        );
});

it('prices a collectible instruction without treating its target as outbound cash', function () {
    $instructions = validVoucherInstructions(0, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'collectible',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    expect($estimate['total_minor'])->toBe(1_500)
        ->and($estimate['charges'])->toHaveCount(1)
        ->and($estimate['charges'][0]['index'])->toBe('cash.amount')
        ->and($estimate['charges'][0]['catalog_item_reference'])
        ->toBe('flow_type.collectible');
});

it('prices plain collection voucher types from their specific governed catalog item', function (string $voucherType): void {
    $catalog = config('x-commerce.catalogs.pay_code');
    $catalog['version'] = 4;
    $catalog['items']['voucher_type.payable']['unit_price_minor'] = 0;
    $catalog['items']['voucher_type.settlement']['unit_price_minor'] = 0;
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    $instructions = validVoucherInstructions(250, 'INSTAPAY', [
        'voucher_type' => $voucherType,
        'target_amount' => 250,
        'metadata' => [],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    expect($estimate['catalog_version'])->toBe(4)
        ->and($estimate['total_minor'])->toBe(0)
        ->and(collect($estimate['charges'])->pluck('catalog_item_reference')->all())
        ->toBe(['voucher_type.'.$voucherType]);
})->with(['payable', 'settlement']);

it('continues to price optional features on a zero-priced payable voucher', function (): void {
    $catalog = config('x-commerce.catalogs.pay_code');
    $catalog['version'] = 4;
    $catalog['items']['voucher_type.payable']['unit_price_minor'] = 0;
    $catalog['items']['voucher_type.settlement']['unit_price_minor'] = 0;
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    $instructions = validVoucherInstructions(250, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => 250,
        'metadata' => [],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => 'payer@example.test',
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);
    $charges = collect($estimate['charges'])->keyBy('catalog_item_reference');

    expect($estimate['total_minor'])->toBe(150)
        ->and($charges->keys()->all())->toBe([
            'voucher_type.payable',
            'feedback.email',
        ])
        ->and($charges->get('feedback.email')['price_minor'])->toBe(150);
});

it('uses a no-payout waterfall for Account Funding Pay Codes', function () {
    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'metadata' => [
            'custom' => [
                'settlement' => [
                    'destinations' => ['account_funding'],
                    'account_funding' => [
                        'pricing_profile' => 'account-funding-v1',
                    ],
                ],
            ],
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    expect($estimate['total_minor'])->toBe(1_500)
        ->and($estimate['waterfall_policy_reference'])
        ->toBe('pay-code-account-funding-waterfall');
});

it('rejects dual-outcome pricing until execution-cost reserves are active', function () {
    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'metadata' => [
            'custom' => [
                'settlement' => [
                    'destinations' => [
                        'provider_payout',
                        'account_funding',
                    ],
                    'account_funding' => [
                        'pricing_profile' => 'account-funding-v1',
                    ],
                ],
            ],
        ],
    ]);

    expect(fn () => app(PricingServiceContract::class)->estimate($instructions))
        ->toThrow(
            PayCodeIssuanceFailed::class,
            'Dual-outcome Pay Codes remain disabled until execution-cost reserves are active.',
        );
});

it('prices every Pay Code in a batch with the same catalog quantities', function () {
    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'count' => 3,
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    expect($estimate['total_minor'])->toBe(4_500)
        ->and($estimate['charges'])->toHaveCount(1)
        ->and($estimate['charges'][0]['quantity'])->toBe(3)
        ->and($estimate['charges'][0]['price_minor'])->toBe(4_500);
});

it('preserves canonical catalog references behind the legacy allocation index', function () {
    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => 100.00,
        'cash' => [
            'validation' => [
                'secret' => 'required-secret',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Thank you',
        ],
    ]);

    $charges = collect(app(PricingServiceContract::class)->estimate($instructions)['charges'])
        ->keyBy('catalog_item_reference');

    expect($charges->get('voucher_type.payable')['index'])->toBe('cash.amount')
        ->and($charges->get('cash.validation.secret')['index'])->toBe('cash.amount')
        ->and($charges->get('rider.message')['index'])->toBe('cash.amount');
});
