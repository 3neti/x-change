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
        ->assertJsonPath('result.code', 'FUND-QUICK-001');

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
