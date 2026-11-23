<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\DirectQrFundingInstructionIssuer;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\VerifyFundingIntent;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\ReserveNetbankVca;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
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

it('reserves a seven digit reference once and avoids locally recorded funding addresses', function () {
    $first = app(CreateFundingIntent::class)->handle(issueFundingIntentData());
    $second = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'idempotencyKey' => 'second-vca-reservation',
    ]));
    $allocator = new class extends ReserveNetbankVca
    {
        private array $references = ['0000001', '0000001', '0000002'];

        protected function nextReference(): string
        {
            return array_shift($this->references);
        }
    };

    $address = $allocator->reserve($first, '89628');
    expect($address)->toBe('896280000001')
        ->and($allocator->reserve($first, '89628'))->toBe($address)
        ->and($allocator->reserve($second, '89628'))->toBe('896280000002')
        ->and(DB::table('xchange_funding_vca_reservations')->count())->toBe(2)
        ->and(DB::table('xchange_funding_vca_reservations')->first()->address_ciphertext)->not->toContain($address);

    expect(fn () => $allocator->reserve($first, '91500'))->toThrow(LogicException::class);
});

it('skips a VCA already present in funding intent history', function () {
    $existing = app(CreateFundingIntent::class)->handle(issueFundingIntentData());
    $next = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'idempotencyKey' => 'historical-vca-reservation',
    ]));
    $key = config('x-change.funding.reference_hash_key') ?: config('app.key');
    DB::table('x_change_funding_intents')->where('id', $existing->getKey())->update([
        'funding_address_hash' => hash_hmac('sha256', '896280000001', $key),
    ]);
    $allocator = new class extends ReserveNetbankVca
    {
        private array $references = ['0000001', '0000002'];

        protected function nextReference(): string
        {
            return array_shift($this->references);
        }
    };

    expect($allocator->reserve($next, '89628'))->toBe('896280000002');
});

it('does not reuse an address hashed with a previous funding reference key', function () {
    $existing = app(CreateFundingIntent::class)->handle(issueFundingIntentData());
    $next = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'idempotencyKey' => 'previous-key-vca-reservation',
    ]));
    $oldKey = 'old-reference-key';
    config()->set('x-change.funding.reference_hash_key', 'new-reference-key');
    config()->set('x-change.funding.previous_reference_hash_keys', [$oldKey]);
    DB::table('x_change_funding_intents')->where('id', $existing->getKey())->update([
        'funding_address_hash' => hash_hmac('sha256', '896280000001', $oldKey),
    ]);
    $allocator = new class extends ReserveNetbankVca
    {
        private array $references = ['0000001', '0000002'];

        protected function nextReference(): string
        {
            return array_shift($this->references);
        }
    };

    expect($allocator->reserve($next, '89628'))->toBe('896280000002');
});

it('checks retained application keys for addresses generated before key rotation', function () {
    $existing = app(CreateFundingIntent::class)->handle(issueFundingIntentData());
    $next = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'idempotencyKey' => 'previous-app-key-vca-reservation',
    ]));
    config()->set('x-change.funding.reference_hash_key', 'current-reference-key');
    $oldApplicationKey = 'base64:'.base64_encode(random_bytes(32));
    config()->set('app.previous_keys', [$oldApplicationKey]);
    DB::table('x_change_funding_intents')->where('id', $existing->getKey())->update([
        'funding_address_hash' => hash_hmac('sha256', '896280000001', $oldApplicationKey),
    ]);
    $allocator = new class extends ReserveNetbankVca
    {
        private array $references = ['0000001', '0000002'];

        protected function nextReference(): string
        {
            return array_shift($this->references);
        }
    };

    expect($allocator->reserve($next, '89628'))->toBe('896280000002');
});

it('excludes previously used provider addresses even when they are absent from local funding history', function () {
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData());
    $allocator = new class extends ReserveNetbankVca
    {
        private array $references = ['0000001', '0000002'];

        protected function nextReference(): string
        {
            return array_shift($this->references);
        }
    };

    $allocator->recordHistoricalAddress('896280000001');
    $allocator->recordHistoricalAddress('896280000001');
    expect($allocator->reserve($intent, '89628'))->toBe('896280000002');
    expect(fn () => $allocator->recordHistoricalAddress('896280000002'))->toThrow(LogicException::class);
});

it('issues dedicated funding only for its frozen account and reserved address', function () {
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $owner = actingAsTestUser(0);
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding'],
    ])->save();
    app()->instance(TreasuryProviderConnectionCatalog::class,
        new TreasuryProviderConnectionCatalog([
            'personal' => [
                'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
                'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
                'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
            ],
        ]));
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'personal');
    $wallet = $owner->wallet()->where('slug', 'platform')->firstOrFail();
    $destination = new FundingDestinationData(
        provider: 'netbank', mode: 'dedicated', destinationType: 'bank_account', accountReference: 'wallet:'.$wallet->uuid,
        displayReference: 'Dedicated', fingerprint: hash('sha256', 'personal'), verificationStatus: 'routing_configured',
        bankAccountNumber: '991100001234', bankAccountName: 'Example', routingAlias: '54321', metadata: [
            'assigned_bank_account_link_id' => $assignment->provider_account_link_id,
            'treasury_connection_reference' => 'personal', 'treasury_inventory_reference' => 'inventory:personal',
            'treasury_resource_reference' => 'resource:personal',
        ],
    );
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'accountReference' => 'wallet:'.$wallet->uuid, 'destination' => $destination,
    ]));
    $link = $assignment->providerAccountLink;
    $link->forceFill(['metadata' => ['funding_assignment' => [
        'scope' => 'fresh_intents_only', 'fresh_since' => now()->addMinute()->toIso8601String(),
    ]]])->save();
    expect(fn () => app(IssueFundingInstructions::class)->handle($intent, 'operator', '42'))
        ->toThrow(FundingDestinationUnavailable::class, 'Only new funding requests');
    expect($intent->refresh()->status)->toBe(FundingIntentStatus::PendingInstructions)
        ->and($this->fundingAdapter->instructionCalls)->toBe(0);
    expect(fn () => app(VerifyFundingIntent::class)->handle(
        $intent, new FundingIntentVerificationData(
            FundingVerificationTrigger::Operator, 'fixture'
        )
    ))->toThrow(FundingDestinationUnavailable::class, 'Only new funding requests');
    $intent->forceFill(['status' => FundingIntentStatus::Verified])->saveQuietly();
    expect(fn () => app(SettleVerifiedFundingIntent::class)->handle($intent))
        ->toThrow(FundingDestinationUnavailable::class, 'Only new funding requests');
    $intent->forceFill(['status' => FundingIntentStatus::PendingInstructions])->saveQuietly();
    $link->forceFill(['metadata' => ['funding_assignment' => [
        'scope' => 'fresh_intents_only', 'fresh_since' => now()->subMinute()->toIso8601String(),
    ]]])->save();
    app()->instance(ReserveNetbankVca::class, new class extends ReserveNetbankVca
    {
        protected function nextReference(): string
        {
            return '1234567';
        }
    });
    $this->fundingAdapter->instructions = new FundingInstructionsData(
        provider: 'netbank', providerReference: '543211234568', amountMinor: 25_000, currency: 'PHP',
        fundingAddress: '543211234568',
    );
    expect(fn () => app(IssueFundingInstructions::class)->handle($intent, 'operator', '42'))
        ->toThrow(InvalidArgumentException::class, 'different funding address');
    expect($intent->refresh()->status)->toBe(FundingIntentStatus::PendingInstructions);
    $this->fundingAdapter->instructions = new FundingInstructionsData(
        provider: 'netbank', providerReference: '543211234567', amountMinor: 25_000, currency: 'PHP',
        fundingAddress: '543211234567',
    );

    $issued = app(IssueFundingInstructions::class)->handle($intent, 'operator', '42');
    expect($issued->funding_address_ciphertext)->toBe('543211234567')
        ->and($issued->status)->toBe(FundingIntentStatus::AwaitingFunds)
        ->and(app(IssueFundingInstructions::class)->handle($intent, 'operator', '42')->funding_address_ciphertext)->toBe('543211234567');

    $destination->metadata['netbank_instruction_mode'] = 'direct_qr';
    $directIntent = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'accountReference' => 'wallet:'.$wallet->uuid, 'destination' => $destination,
        'idempotencyKey' => 'dedicated-direct-qr-test',
    ]));
    $directAdapter = new class extends FakeFundingProviderAdapter implements DirectQrFundingInstructionIssuer
    {
        public int $directCalls = 0;

        public function createDirectQrFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
        {
            $this->directCalls++;
            $address = $request->destination->routingAlias.$request->metadata['reserved_vca_reference'];

            return new FundingInstructionsData(provider: 'netbank', providerReference: $address,
                amountMinor: $request->amountMinor, currency: $request->currency, fundingAddress: $address);
        }
    };
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$directAdapter]));
    expect(fn () => app(IssueFundingInstructions::class)->handle($directIntent, 'operator', '42'))
        ->toThrow(FundingDestinationUnavailable::class, 'funding mode is unavailable');
    expect($directAdapter->directCalls)->toBe(0);
    config()->set('x-change.funding.dedicated_netbank_direct_qr_enabled', true);
    app()->instance(ReserveNetbankVca::class, new class extends ReserveNetbankVca
    {
        protected function nextReference(): string
        {
            return '7654321';
        }
    });
    $directIssued = app(IssueFundingInstructions::class)->handle($directIntent, 'operator', '42');
    expect($directIssued->funding_address_ciphertext)->toBe('543217654321')
        ->and(data_get($directIssued->metadata, 'direct_qr_monitor'))->toBeTrue()
        ->and($directAdapter->directCalls)->toBe(1)
        ->and($directAdapter->instructionCalls)->toBe(0);

    config()->set('x-change.funding.dedicated_netbank_vca_enabled', false);
    $callsBeforeRecovery = $this->fundingAdapter->instructionCalls;
    $recovered = app(IssueFundingInstructions::class)->handle($intent, 'operator', '42');

    expect($recovered->funding_address_ciphertext)->toBe('543211234567')
        ->and($recovered->version)->toBe($issued->version)
        ->and($this->fundingAdapter->instructionCalls)->toBe($callsBeforeRecovery);
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

it('uses registered VCA fixed-amount QR Ph instructions only when explicitly selected', function () {
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'registered_vca');
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

it('uses the shared direct NetBank QR issuer without token registration or an exact provider limit', function () {
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'direct_qr');
    config()->set('payment-gateway.netbank.funding.reference_key', 'direct-qr-reference-key');
    $qrPayload = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldNotReceive('generateAliasToken');
    $client->shouldNotReceive('registerPreTransactionReference');
    $client->shouldNotReceive('createExactLimit');
    $client->shouldReceive('generateQrCode')
        ->once()
        ->withArgs(fn (string $vcaNumber, int $amountMinor, string $currency): bool => preg_match('/\A90627\d{16}\z/', $vcaNumber) === 1
            && $amountMinor === 25_000
            && $currency === 'PHP')
        ->andReturn($qrPayload);
    $this->app->instance(NetbankFundingApiClient::class, $client);
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([
        app(NetbankFundingProviderAdapter::class),
    ]));

    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expiresAt' => new DateTimeImmutable('+30 minutes'),
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
        ->and(data_get($issued->instructions_ciphertext, 'amount_minor'))->toBe(25_000)
        ->and(data_get($issued->instructions_ciphertext, 'display_data.amount_control'))->toBe('qr-embedded')
        ->and(data_get($issued->instructions_ciphertext, 'display_data.address_registration'))->toBe('not-requested')
        ->and(data_get($issued->instructions_ciphertext, 'qr_code.embedded_amount'))->toBeTrue()
        ->and($this->fundingAdapter->instructionCalls)->toBe(0);
});

it('fails closed for an unsupported NetBank QR mode', function () {
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'automatic_fallback');
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData([
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
    ]));

    expect(fn () => app(IssueFundingInstructions::class)->handle($intent, 'operator', '42'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported NetBank on-demand QR mode');

    expect($intent->fresh()->status)->toBe(FundingIntentStatus::PendingInstructions)
        ->and($this->fundingAdapter->instructionCalls)->toBe(0);
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

it('does not issue payment instructions for a dedicated NetBank destination without isolated Treasury', function () {
    $destination = new FundingDestinationData(
        provider: 'netbank', mode: 'dedicated', destinationType: 'bank_account',
        accountReference: 'wallet:account-1001', displayReference: 'Example', fingerprint: 'example',
        verificationStatus: 'assigned', bankAccountNumber: '991100001234',
    );
    $intent = app(CreateFundingIntent::class)->handle(issueFundingIntentData(['destination' => $destination]));
    expect(fn () => app(IssueFundingInstructions::class)->handle($intent, 'operator', '42'))
        ->toThrow(FundingDestinationUnavailable::class, 'not available yet');
    expect($intent->refresh()->status)->toBe(FundingIntentStatus::PendingInstructions)
        ->and($this->fundingAdapter->instructionCalls)->toBe(0);
});
