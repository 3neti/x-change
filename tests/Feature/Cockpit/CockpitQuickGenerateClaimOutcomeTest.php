<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;

it('hands a canonical Account Funding claim policy to Quick Generate issuance', function (): void {
    $generator = new class extends GeneratePayCode
    {
        /**
         * @var list<array<string, mixed>>
         */
        public array $payloads = [];

        public function __construct() {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->payloads[] = $input;

            return new GeneratePayCodeResultData(
                voucher_id: 991,
                code: 'FUND-QUICK-001',
                amount: 125,
                currency: 'PHP',
                issuer: new IssuerData(id: 1),
                cost: new PricingEstimateData(
                    currency: 'PHP',
                    base_fee: 0,
                    components: [],
                    total: 0,
                    charges: [],
                ),
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/x/claim/FUND-QUICK-001/experience',
                    redeem_path: '/x/claim/FUND-QUICK-001/experience',
                ),
                allocations: [],
            );
        }
    };

    app()->instance(GeneratePayCode::class, $generator);
    actingAsTestUser();

    $this->postJson(route('x-change.cockpit.quick-generate.store'), [
        'cash' => [
            'amount' => 125,
            'currency' => 'PHP',
            'validation' => [],
        ],
        'inputs' => [
            'fields' => [],
            'requirements' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Add this Pay Code to your Account.',
            'url' => null,
            'splash' => null,
        ],
        'count' => 1,
        'provider' => 'netbank',
        'claim' => [
            'outcomes' => [[
                'key' => 'account_funding',
                'pricing_profile' => 'account-funding-v1',
            ]],
            'selection' => 'server',
            'consumption' => 'one_of',
            'default_outcome' => 'account_funding',
            'onboarding' => ['mode' => 'if_required'],
            'claimant' => ['mode' => 'unbound'],
            'profile' => 'voucher.claim.v1',
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                    'source' => 'cockpit.quick-generate',
                    'recipient_reference' => 'CASH',
                ],
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('result.code', 'FUND-QUICK-001')
        ->assertJsonPath('result.claim.outcome', 'account_funding')
        ->assertJsonPath('result.claim.label', 'Account funds')
        ->assertJsonPath('result.claim.provider_payout', false)
        ->assertJsonPath('result.claim.account_funding', true)
        ->assertJsonPath(
            'post_issuance_navigation.items.4.key',
            'account_funding',
        )
        ->assertJsonPath(
            'post_issuance_navigation.items.4.href',
            '/x/cockpit/funding?mode=pay_code',
        )
        ->assertJsonPath(
            'post_issuance_navigation.items.4.enabled',
            true,
        );

    expect($generator->payloads)->toHaveCount(1)
        ->and(data_get($generator->payloads[0], 'claim'))->toMatchArray([
            'outcomes' => [[
                'key' => 'account_funding',
                'pricing_profile' => 'account-funding-v1',
            ]],
            'selection' => 'server',
            'consumption' => 'one_of',
            'default_outcome' => 'account_funding',
            'claimant' => ['mode' => 'unbound'],
        ])
        ->and(data_get(
            $generator->payloads[0],
            'metadata.custom.cockpit.recipient_reference',
        ))->toBeNull();
});

it('opens the Funding workspace on the Pay Code tab without placing a code in the URL', function (): void {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index', [
            'mode' => 'pay_code',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Funding')
        ->assertJsonPath('props.funding_workspace_mode', 'pay_code');
});
