<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Onboarding\Actions\PromoteContactToUser;
use LBHurtado\Onboarding\Contracts\ContactUserProvisionerContract;
use LBHurtado\Onboarding\Data\ContactPromotionResultData;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;
use LBHurtado\XChange\Services\Execution\OnboardingAccountProvisioningExecutionDriver;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use LBHurtado\XChange\Services\Onboarding\DefaultAccountProvisioningService;
use LBHurtado\XChange\Services\Onboarding\OnboardingCredentialPolicy;
use LBHurtado\XChange\Services\Onboarding\OnboardingVoucherClaimantAuthenticator;
use LBHurtado\XChange\Services\Onboarding\XChangeContactUserProvisioner;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Tests\Fakes\User;
use Propaganistas\LaravelPhone\PhoneNumber;

function onboardingExecutionContext(array $inputs, bool $verificationRequired = true): ExecutionContextData
{
    $contact = Contact::fromPhoneNumber(new PhoneNumber('09173011987', 'PH'));
    $voucher = (new Voucher)->forceFill([
        'code' => 'ONBD-1234',
        'metadata' => [
            'instructions' => [
                'execution' => [
                    'schema' => ExecutionInstructionData::SCHEMA,
                    'driver' => OnboardingVoucherInstructionPolicy::ExecutionDriver,
                ],
            ],
        ],
    ]);

    return new ExecutionContextData(
        contact: $contact,
        voucherCode: 'ONBD-1234',
        meta: ['inputs' => $inputs],
        voucher: $voucher,
        instruction: new ExecutionInstructionData(
            driver: OnboardingVoucherInstructionPolicy::ExecutionDriver,
            metadata: [
                'onboarding' => [
                    'mobile_verification_required' => $verificationRequired,
                ],
            ],
        ),
    );
}

it('registers the onboarding account provisioning execution driver', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry->has(OnboardingVoucherInstructionPolicy::ExecutionDriver))->toBeTrue()
        ->and($registry->resolve(OnboardingVoucherInstructionPolicy::ExecutionDriver))
        ->toBeInstanceOf(OnboardingAccountProvisioningExecutionDriver::class);
});

it('persists an execution-only post-redemption policy for onboarding Vouchers', function (): void {
    $normalized = app(OnboardingVoucherInstructionPolicy::class)->normalize([
        'onboarding' => true,
        'cash' => [
            'amount' => 1,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
    ]);

    expect(data_get(
        $normalized,
        'execution.metadata.post_redemption.mode',
    ))->toBe(OnboardingVoucherInstructionPolicy::PostRedemptionMode);
});

it('fails before account mutation when required mobile verification evidence is absent', function () {
    $provisioner = Mockery::mock(ContactUserProvisionerContract::class);
    $provisioner->shouldNotReceive('provision');

    $defaultDriver = Mockery::mock(DefaultExecutionDriver::class);
    $defaultDriver->shouldNotReceive('execute');

    $driver = new OnboardingAccountProvisioningExecutionDriver(
        new PromoteContactToUser($provisioner),
        $defaultDriver,
        app(DispatchVoucherClaimOutcome::class),
        app(OnboardingVoucherClaimantAuthenticator::class),
        Request::create('/x/claim/ONBD-1234'),
    );

    $result = $driver->execute(onboardingExecutionContext([
        'full_name' => 'Maria Santos',
        'email' => 'maria@example.test',
    ]));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('mobile_verification_required');
});

it('provisions the account and strips raw OTP evidence before Voucher redemption', function () {
    $user = actingAsTestUser();
    auth()->logout();

    $provisioner = Mockery::mock(ContactUserProvisionerContract::class);
    $provisioner->shouldReceive('provision')
        ->once()
        ->withArgs(fn (mixed $contact, array $attributes): bool => $contact instanceof Contact
            && $attributes['name'] === 'Maria Santos'
            && $attributes['email'] === 'maria@example.test'
            && $attributes['mobile_verified'] === true)
        ->andReturn(new ContactPromotionResultData(
            promoted: true,
            user: $user,
            meta: [
                'reused' => false,
                'principal_reference' => 'principal:account:test',
                'position_count' => 2,
            ],
        ));

    $defaultDriver = Mockery::mock(DefaultExecutionDriver::class);
    $defaultDriver->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(function (ExecutionContextData $context): bool {
            $inputs = (array) data_get($context->meta, 'inputs', []);

            return ! array_key_exists('otp_code', $inputs)
                && data_get($inputs, 'otp.verified_at') === '2026-07-30T12:00:00+08:00'
                && data_get($inputs, 'otp.otp_code') === null
                && data_get($inputs, 'otp.value') === 'verified'
                && data_get($inputs, 'otp.verified') === true
                && data_get($inputs, 'verified_at') === '2026-07-30T12:00:00+08:00';
        }))
        ->andReturn(ExecutionResultData::succeeded('default'));

    $driver = new OnboardingAccountProvisioningExecutionDriver(
        new PromoteContactToUser($provisioner),
        $defaultDriver,
        app(DispatchVoucherClaimOutcome::class),
        app(OnboardingVoucherClaimantAuthenticator::class),
        Request::create('/x/claim/ONBD-1234'),
    );

    $result = $driver->execute(onboardingExecutionContext([
        'full_name' => 'Maria Santos',
        'email' => 'maria@example.test',
        'otp_code' => '123456',
        'otp' => [
            'otp_code' => '123456',
            'verified_at' => '2026-07-30T12:00:00+08:00',
        ],
        'verified_at' => '2026-07-30T12:00:00+08:00',
    ]));

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe(OnboardingVoucherInstructionPolicy::ExecutionDriver)
        ->and($result->metadata)->not->toHaveKey('mobile')
        ->and($result->metadata)->not->toHaveKey('email')
        ->and(json_encode($result->toArray()))->not->toContain('123456');
});

it('does not require OTP evidence when the persisted onboarding policy disabled it', function () {
    $user = actingAsTestUser();
    auth()->logout();

    $provisioner = Mockery::mock(ContactUserProvisionerContract::class);
    $provisioner->shouldReceive('provision')
        ->once()
        ->andReturn(new ContactPromotionResultData(
            promoted: true,
            user: $user,
            meta: ['reused' => true, 'position_count' => 2],
        ));

    $defaultDriver = Mockery::mock(DefaultExecutionDriver::class);
    $defaultDriver->shouldReceive('execute')
        ->once()
        ->andReturn(ExecutionResultData::succeeded('default'));

    $driver = new OnboardingAccountProvisioningExecutionDriver(
        new PromoteContactToUser($provisioner),
        $defaultDriver,
        app(DispatchVoucherClaimOutcome::class),
        app(OnboardingVoucherClaimantAuthenticator::class),
        Request::create('/x/claim/ONBD-1234'),
    );

    expect($driver->execute(onboardingExecutionContext([
        'full_name' => 'Maria Santos',
        'email' => 'maria@example.test',
    ], false))->successful)->toBeTrue();
});

it('rolls back a newly provisioned Account when Voucher redemption fails', function () {
    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldReceive('open')->once()->andReturn((object) ['id' => 1]);

    $portfolios = Mockery::mock(TreasuryAccountPortfolioProvisioningContract::class);
    $portfolios->shouldReceive('provision')
        ->once()
        ->andReturn(new TreasuryAccountPortfolioData(
            principalReference: 'principal:account:rollback',
            positions: [],
            skippedConnections: [],
        ));

    $defaultDriver = Mockery::mock(DefaultExecutionDriver::class);
    $defaultDriver->shouldReceive('execute')
        ->once()
        ->andReturn(ExecutionResultData::failed('default', 'compatibility_redemption_rejected'));

    $driver = new OnboardingAccountProvisioningExecutionDriver(
        new PromoteContactToUser(new XChangeContactUserProvisioner(
            new DefaultAccountProvisioningService($wallets, $portfolios),
            app(AccountPinSetupState::class),
            app(OnboardingCredentialPolicy::class),
        )),
        $defaultDriver,
        app(DispatchVoucherClaimOutcome::class),
        app(OnboardingVoucherClaimantAuthenticator::class),
        Request::create('/x/claim/ONBD-1234'),
    );

    $result = $driver->execute(onboardingExecutionContext([
        'full_name' => 'Rollback Recipient',
        'email' => 'rollback@example.test',
    ], false));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('compatibility_redemption_rejected')
        ->and(User::query()->where('email', 'rollback@example.test')->exists())->toBeFalse();
});

it('authenticates the provisioned claimant and regenerates the browser session', function () {
    $user = User::query()->create([
        'name' => 'Authenticated Recipient',
        'email' => 'authenticated-recipient@example.test',
        'mobile' => '639171111111',
        'password' => bcrypt('unused-random-password'),
    ]);
    $request = Request::create('/x/claim/ONBD-1234');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    $session->put('x-change.auth.intent', ['type' => 'onboarding_claimant_handoff']);
    $oldSessionId = $session->getId();

    $authenticated = app(OnboardingVoucherClaimantAuthenticator::class)
        ->authenticate($user, $request);

    expect($authenticated)->toBeTrue()
        ->and(auth()->user()?->is($user))->toBeTrue()
        ->and($session->getId())->not->toBe($oldSessionId)
        ->and($session->has('x-change.auth.intent'))->toBeFalse();
});
