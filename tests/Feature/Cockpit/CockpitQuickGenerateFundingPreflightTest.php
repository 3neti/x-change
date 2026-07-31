<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\BuildBalanceOverview;

it('adds operator safe funding preflight metadata before quick generate issuance', function () {
    app()->instance(BuildBalanceOverview::class, new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true, bool $forceSync = false): array
        {
            return [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
                'authority' => 'local_ledger',
                'sync_status' => 'not_required',
                'authoritative' => [
                    'key' => 'local_ledger',
                    'authority' => 'local_ledger',
                    'source' => 'bavix_wallet',
                    'balance' => 10000,
                    'currency' => 'PHP',
                    'is_stale' => false,
                ],
            ];
        }
    });

    app()->instance(EstimatePayCodeCost::class, cockpitWave10FundingPricingFake());
    app()->instance(GeneratePayCode::class, cockpitWave10FundingGeneratePayCodeFake('PC-WAVE-10E'));

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave10FundingPayload())
        ->assertCreated()
        ->assertJsonPath('preflight.funding.status', 'checked')
        ->assertJsonPath('preflight.funding.provider', 'netbank')
        ->assertJsonPath('preflight.funding.topology', 'ledger_pooled')
        ->assertJsonPath('preflight.funding.authority', 'local_ledger')
        ->assertJsonPath('preflight.funding.authoritative.key', 'local_ledger')
        ->assertJsonPath('preflight.funding.authoritative.balance', 10000)
        ->assertJsonPath('preflight.funding.authoritative.currency', 'PHP')
        ->assertJsonPath('preflight.funding.blocking', false)
        ->assertJsonPath('preflight.funding.source', 'BuildBalanceOverview')
        ->assertJsonPath('result.code', 'PC-WAVE-10E')
        ->assertJsonMissingPath('preflight.funding.balances')
        ->assertJsonMissingPath('preflight.funding.provider_wallet_id');
});

it('keeps quick generate issuance non blocking when funding preflight is unavailable', function () {
    app()->instance(BuildBalanceOverview::class, new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true, bool $forceSync = false): array
        {
            throw new RuntimeException('funding unavailable');
        }
    });

    app()->instance(EstimatePayCodeCost::class, cockpitWave10FundingPricingFake());
    app()->instance(GeneratePayCode::class, cockpitWave10FundingGeneratePayCodeFake('PC-WAVE-10E-FALLBACK'));

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave10FundingPayload())
        ->assertCreated()
        ->assertJsonPath('preflight.funding.status', 'unavailable')
        ->assertJsonPath('preflight.funding.blocking', false)
        ->assertJsonPath('preflight.funding.source', 'BuildBalanceOverview')
        ->assertJsonPath('preflight.funding.reason', RuntimeException::class)
        ->assertJsonPath('result.code', 'PC-WAVE-10E-FALLBACK');
});

function cockpitWave10FundingPricingFake(): EstimatePayCodeCost
{
    return new class extends EstimatePayCodeCost
    {
        public function __construct() {}

        public function handle(array $input): PricingEstimateData
        {
            return new PricingEstimateData(currency: 'PHP', total: 0);
        }
    };
}

function cockpitWave10FundingGeneratePayCodeFake(string $code): GeneratePayCode
{
    return new class($code) extends GeneratePayCode
    {
        public function __construct(private readonly string $code) {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
                wallet: ['balance_before' => 100000, 'balance_after' => 99975],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/'.$this->code,
                    redeem_path: '/r/'.$this->code,
                ),
            );
        }
    };
}

/**
 * @return array<string, mixed>
 */
function cockpitWave10FundingPayload(): array
{
    return [
        'cash' => [
            'amount' => '25.00',
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => '09173011987',
        ],
        'rider' => [
            'message' => 'Wave 10E funding preflight',
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                ],
            ],
        ],
    ];
}
