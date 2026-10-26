<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('x-change.public_auto_register.provider', 'qrph_simulator');
    config()->set('x-change.public_auto_register.amount_minor', 10_000);
    config()->set('x-change.funding.simulator.enabled', true);
    config()->set('x-change.funding.providers.qrph_simulator.enabled', true);
    config()->set('x-change.public_auto_register.treasury_connection_reference', 'netbank-primary');
    config()->set('x-change.funding.providers.qrph_simulator.treasury', [
        'inventory_reference' => 'inventory:qrph-simulator:vca-cash',
        'settlement_resource_reference' => 'resource:qrph-simulator:corporate-vca',
        'resource_type' => 'cash_at_bank',
    ]);
    config()->set('x-change.funding.simulator.signing_key', 'public-register-retry-signing-key');
    config()->set('x-change.funding.simulator.mobile_hash_key', 'public-register-retry-mobile-key');
    config()->set('x-change.funding.payer_identity_hash_key', 'public-register-retry-payer-key');
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.commercial.billing.mode', 'billable');
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.principal.reference', 'public-register-retry-commercial');
    config()->set('x-change.commercial.principal.legal_name', 'Public Registration Retry Commercial Principal');
    config()->set('x-change.commercial.principal.authorization_reference', 'commissioning:public-register-retry:commercial');
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-register-retry-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Public Registration Retry Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'commissioning:public-register-retry:escrow');
    CommercialPrincipal::query()->create([
        'reference' => 'public-register-retry-escrow',
        'legal_name' => 'Public Registration Retry Escrow',
        'authorization_reference' => 'commissioning:public-register-retry:escrow',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:public-register-retry');
    app(CommercialPrincipalProvisioningService::class)->provision();

    $operator = FakeLifecycleUser::query()->create([
        'name' => 'Registration Retry Operator',
        'email' => 'public-register-retry@example.test',
        'password' => bcrypt('password'),
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ]);
    fundTestUserWallet($operator);
    fundTestUserClientFunds($operator, 10_000);
    fundTestUserClientFunds(CommercialPrincipal::query()->where('reference', 'public-register-retry-escrow')->firstOrFail(), 10_000);
    $this->actingAs($operator);
});

it('returns 503 on a NetBank instruction failure and reuses the funding intent on retry', function (): void {
    $realIssuer = app(IssueFundingInstructions::class);
    $attempts = 0;
    $issuer = Mockery::mock(IssueFundingInstructions::class);
    $issuer->shouldReceive('handle')->twice()->andReturnUsing(function (...$arguments) use ($realIssuer, &$attempts) {
        if (++$attempts === 1) {
            throw NetbankFundingRequestFailed::forOperation('generate-vca-alias-token', 500);
        }

        return $realIssuer->handle(...$arguments);
    });
    app()->instance(IssueFundingInstructions::class, $issuer);

    $this->postJson(route('x-change.public-auto-register.start'), [
        'idempotency_key' => 'stable-registration-attempt',
    ])->assertStatus(503)->assertJsonPath('message', 'Payment setup is temporarily unavailable. Please retry this registration. No payment has been requested.');

    expect(FundingIntent::query()->count())->toBe(1)
        ->and(FundingIntent::query()->sole()->status)->toBe(FundingIntentStatus::PendingInstructions)
        ->and(PublicAutoRegistration::query()->count())->toBe(0);

    $this->postJson(route('x-change.public-auto-register.start'), [
        'idempotency_key' => 'stable-registration-attempt',
    ])->assertCreated()->assertJsonPath('status', 'awaiting_payment');

    expect(FundingIntent::query()->count())->toBe(1)
        ->and(PublicAutoRegistration::query()->count())->toBe(1);
});

it('returns 422 for an expired attempt key before requesting provider instructions again', function (): void {
    $issuer = Mockery::mock(IssueFundingInstructions::class);
    $issuer->shouldReceive('handle')->once()->andThrow(
        NetbankFundingRequestFailed::forOperation('generate-vca-alias-token', 500),
    );
    app()->instance(IssueFundingInstructions::class, $issuer);

    $this->postJson(route('x-change.public-auto-register.start'), [
        'idempotency_key' => 'expired-registration-attempt',
    ])->assertStatus(503);
    $this->travel(31)->minutes();

    $this->postJson(route('x-change.public-auto-register.start'), [
        'idempotency_key' => 'expired-registration-attempt',
    ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

    expect(FundingIntent::query()->count())->toBe(1)
        ->and(PublicAutoRegistration::query()->count())->toBe(0);
});
