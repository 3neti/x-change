<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

function issueFundingIntentData(array $overrides = []): CreateFundingIntentData
{
    return new CreateFundingIntentData(...array_merge([
        'accountReference' => 'wallet:account-1001',
        'provider' => 'netbank',
        'expectedAmountMinor' => 25_000,
        'currency' => 'PHP',
        'idempotencyKey' => 'issue-funding-request-1001',
        'actorType' => 'App\\Models\\User',
        'actorId' => '42',
        'expiresAt' => new DateTimeImmutable('2026-07-23T10:00:00+08:00'),
    ], $overrides));
}

beforeEach(function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $this->fundingAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->fundingAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('encrypts provider instructions and advances the intent without crediting an account', function () {
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData());

    $issued = app(IssueFundingInstructions::class)->handle($intent, 'operator', '42');
    $raw = DB::table('x_change_funding_intents')->find($intent->getKey());

    expect($issued->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and($issued->version)->toBe(2)
        ->and($issued->instructions_created_at)->not->toBeNull()
        ->and($issued->funding_address_ciphertext)->toBe('915001234567890123456')
        ->and($issued->instructions_ciphertext['funding_address'])->toBe('915001234567890123456')
        ->and($issued->instructions_ciphertext['qr_code'])->toMatchArray([
            'mime_type' => 'image/png',
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ])
        ->and($issued->provider_reference)->toStartWith('sha256:')
        ->and($issued->provider_reference)->not->toContain('915001234567890123456')
        ->and($issued->provider_request_id)->toBe('915001234567890123456')
        ->and($issued->funding_address_hash)->toBe(hash_hmac(
            'sha256',
            '915001234567890123456',
            (string) (config('x-change.funding.reference_hash_key') ?: config('app.key')),
        ))
        ->and($raw->funding_address_ciphertext)->not->toContain('915001234567890123456')
        ->and($raw->instructions_ciphertext)->not->toContain('915001234567890123456')
        ->and($raw->instructions_ciphertext)->not->toContain('iVBORw0KGgo')
        ->and($issued->events)->toHaveCount(2)
        ->and($issued->events->last()->event_type)->toBe('provider_instructions_created')
        ->and($issued->events->last()->metadata)->not->toHaveKey('funding_address');
});

it('returns already-issued instructions idempotently', function () {
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData());
    $issue = app(IssueFundingInstructions::class);

    $first = $issue->handle($intent, 'operator', '42');
    $retry = $issue->handle($intent, 'operator', '42');

    expect($retry->getKey())->toBe($first->getKey())
        ->and($retry->version)->toBe(2)
        ->and($retry->events)->toHaveCount(2)
        ->and($this->fundingAdapter->instructionCalls)->toBe(1);
});

it('issues on-demand NetBank bank-transfer instructions without provisioning a VCA', function () {
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'destination' => new FundingDestinationData(
            provider: 'netbank',
            mode: 'shared',
            destinationType: 'bank_account',
            accountReference: 'wallet:account-1001',
            displayReference: 'NetBank corporate account',
            fingerprint: hash('sha256', 'netbank-corporate-account'),
            verificationStatus: 'platform_configured',
            bankAccountNumber: '113-001-00001-9',
            bankAccountName: '3neti R&D OPC',
            routingAlias: '90627',
        ),
    ]));

    $issued = app(IssueFundingInstructions::class)->handle($intent, 'operator', '42');

    expect($issued->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and($issued->funding_address_ciphertext)->toBe('113-001-00001-9')
        ->and($issued->instructions_ciphertext)->toMatchArray([
            'funding_address' => '113-001-00001-9',
            'display_data' => [
                'institution' => 'NetBank',
                'account_name' => '3neti R&D OPC',
                'destination_account' => '113-001-00001-9',
                'amount_minor' => 25_000,
                'currency' => 'PHP',
                'payment_reference' => $intent->reference,
                'one_time' => true,
                'delivery' => 'bank-transfer',
            ],
            'qr_code' => null,
        ])
        ->and($issued->provider_request_id)->toBe('bank-transfer:'.$intent->reference)
        ->and($this->fundingAdapter->instructionCalls)->toBe(0);
});

it('uses provider-issued fixed-amount QR Ph instructions only when explicitly enabled', function () {
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
    ]));

    $issued = app(IssueFundingInstructions::class)->handle($intent, 'operator', '42');

    expect(data_get($issued->instructions_ciphertext, 'amount_minor'))->toBe(25_000)
        ->and(data_get($issued->instructions_ciphertext, 'currency'))->toBe('PHP')
        ->and(data_get($issued->instructions_ciphertext, 'qr_code.qr_mode'))->toBe('dynamic')
        ->and(data_get($issued->instructions_ciphertext, 'qr_code.transaction_type'))->toBe('p2m')
        ->and(data_get($issued->instructions_ciphertext, 'qr_code.embedded_amount'))->toBeTrue()
        ->and(data_get($issued->instructions_ciphertext, 'qr_code.provider_generated'))->toBeTrue()
        ->and($this->fundingAdapter->instructionCalls)->toBe(1);
});

it('fails closed when provider instructions do not match the intent', function () {
    $this->fundingAdapter->instructions = new FundingInstructionsData(
        provider: 'netbank',
        providerReference: 'provider-reference',
        amountMinor: 24_000,
        currency: 'PHP',
        fundingAddress: 'funding-address',
    );
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData());

    expect(fn () => app(IssueFundingInstructions::class)->handle($intent, 'operator', '42'))
        ->toThrow(InvalidArgumentException::class, 'do not match');

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::PendingInstructions)
        ->and($intent->events()->count())->toBe(1);
});
