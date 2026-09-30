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
use LBHurtado\XAffiliation\Models\AffiliationMembership;
use LBHurtado\XAffiliation\Models\AffiliationPath;
use LBHurtado\XAffiliation\Models\AffiliationSponsorship;
use LBHurtado\XChange\Actions\Affiliation\ActivateVoucherSponsorship;
use LBHurtado\XChange\Actions\Affiliation\CreateSponsorshipInvitationAuthority;
use LBHurtado\XChange\Actions\Affiliation\EnrollAffiliationRootAccount;
use LBHurtado\XChange\Actions\Affiliation\LinkSponsorshipAuthorityToVoucher;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\RefreshFundingLiquidity;
use LBHurtado\XChange\Actions\Legal\DeferOnboardingFundingUntilAgreement;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Services\Execution\OnboardingAccountProvisioningExecutionDriver;
use LBHurtado\XChange\Services\Onboarding\OnboardingVoucherClaimantAuthenticator;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XProvisioning\Enums\ProvisioningRequestStatus;
use Propaganistas\LaravelPhone\PhoneNumber;

beforeEach(function (): void {
    config()->set('x-change.affiliation.enabled', true);
    config()->set('x-change.instance.id', 'installation-affiliation-activation-test');
    config()->set('x-affiliation.identity_pepper', 'stable-affiliation-activation-test-pepper');

    $system = affiliationTestUser(
        'System Principal',
        'system-affiliation-activation@example.test',
        '09170000999',
    );
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->getKey(),
            'identifier_column' => $system->getKeyName(),
        ],
    ]);
});

function affiliationTestUser(string $name, string $email, string $mobile): User
{
    $user = (new User)->forceFill([
        'name' => $name,
        'email' => $email,
        'mobile' => $mobile,
        'mobile_verified_at' => now(),
        'password' => bcrypt('unused-password'),
    ]);
    $user->save();

    return $user;
}

function affiliationTestVoucher(string $code): Voucher
{
    return Voucher::query()->create([
        'code' => $code,
        'state' => 'active',
        'metadata' => [
            'instructions' => [
                'onboarding' => true,
            ],
        ],
    ]);
}

function affiliationOnboardingContext(Voucher $voucher, string $mobile): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::fromPhoneNumber(new PhoneNumber($mobile, 'PH')),
        voucherCode: $voucher->code,
        meta: [
            'inputs' => [
                'full_name' => 'Bob Recipient',
                'email' => 'bob-live-path@example.test',
                'mobile' => $mobile,
                'otp' => [
                    'mobile' => $mobile,
                    'verified_at' => now()->toIso8601String(),
                    'verification_reference' => 'otp-affiliation-live-path',
                    'verification_purpose' => 'onboarding.account',
                ],
            ],
        ],
        voucher: $voucher,
        instruction: new ExecutionInstructionData(
            driver: OnboardingVoucherInstructionPolicy::ExecutionDriver,
            metadata: [
                'onboarding' => [
                    'mobile_verification_required' => true,
                ],
            ],
        ),
    );
}

/** @return array{name:string,email:string,mobile:string,otp:bool} */
function affiliationClaimEvidence(User $candidate): array
{
    return [
        'name' => (string) $candidate->name,
        'email' => (string) $candidate->email,
        'mobile' => (string) $candidate->getRawOriginal('mobile'),
        'otp' => true,
    ];
}

it('activates one targeted sponsorship from immutable provisioning authority and replays safely', function (): void {
    $alice = affiliationTestUser('Alice Sponsor', 'alice-sponsor@example.test', '09173011987');
    $maker = affiliationTestUser('Invitation Maker', 'invitation-maker@example.test', '09170000001');
    $checker = affiliationTestUser('Invitation Checker', 'invitation-checker@example.test', '09170000002');
    $bob = affiliationTestUser('Bob Recipient', 'bob-recipient@example.test', '09175180722');
    $alice->wallet()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform Wallet']);

    app(EnrollAffiliationRootAccount::class)->handle($alice, 'commissioning:root:alice:v1');
    $credential = app(CreateSponsorshipInvitationAuthority::class)->handle(
        sponsor: $alice,
        recipientMobile: '09175180722',
        maker: $maker,
        checker: $checker,
    );
    $voucher = affiliationTestVoucher('SPON-BOB1');
    $authority = app(LinkSponsorshipAuthorityToVoucher::class)->handle($voucher, $credential);

    $activated = app(ActivateVoucherSponsorship::class)->handle(
        $voucher,
        $bob,
        affiliationClaimEvidence($bob),
    );
    $replayed = app(ActivateVoucherSponsorship::class)->handle(
        $voucher,
        $bob,
        affiliationClaimEvidence($bob),
    );

    expect($activated)->not->toBeNull()
        ->and($replayed?->is($activated))->toBeTrue()
        ->and(AffiliationMembership::query()->count())->toBe(2)
        ->and(AffiliationSponsorship::query()->count())->toBe(1)
        ->and($authority->getRawOriginal('encrypted_claim_token'))->not->toBe($credential->claimToken)
        ->and(json_encode($activated?->revision?->snapshot))->not->toContain('09175180722');
});

it('rejects a claimant whose verified mobile does not match the targeted invitation', function (): void {
    $alice = affiliationTestUser('Alice Sponsor', 'alice-wrong-mobile@example.test', '09173011987');
    $maker = affiliationTestUser('Invitation Maker', 'maker-wrong-mobile@example.test', '09170000003');
    $checker = affiliationTestUser('Invitation Checker', 'checker-wrong-mobile@example.test', '09170000004');
    $mallory = affiliationTestUser('Mallory Recipient', 'mallory@example.test', '09171234567');
    $alice->wallet()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform Wallet']);

    app(EnrollAffiliationRootAccount::class)->handle($alice, 'commissioning:root:alice:v1');
    $credential = app(CreateSponsorshipInvitationAuthority::class)->handle(
        $alice,
        '09175180722',
        $maker,
        $checker,
    );
    $voucher = affiliationTestVoucher('SPON-WRNG');
    app(LinkSponsorshipAuthorityToVoucher::class)->handle($voucher, $credential);

    expect(fn () => app(ActivateVoucherSponsorship::class)->handle(
        $voucher,
        $mallory,
        affiliationClaimEvidence($mallory),
    ))->toThrow(DomainException::class, 'verified claimant does not match')
        ->and(AffiliationMembership::query()->count())->toBe(1)
        ->and(AffiliationSponsorship::query()->count())->toBe(0);
});

it('builds Alice to Bob to Carol lineage and rejects an invitation back to Alice', function (): void {
    $alice = affiliationTestUser('Alice Sponsor', 'alice-lineage@example.test', '09173011987');
    $bob = affiliationTestUser('Bob Sponsor', 'bob-lineage@example.test', '09175180722');
    $carol = affiliationTestUser('Carol Recipient', 'carol-lineage@example.test', '09171234567');
    $maker = affiliationTestUser('Invitation Maker', 'maker-lineage@example.test', '09170000005');
    $checker = affiliationTestUser('Invitation Checker', 'checker-lineage@example.test', '09170000006');
    $alice->wallet()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform Wallet']);

    app(EnrollAffiliationRootAccount::class)->handle($alice, 'commissioning:root:alice:v1');

    $bobCredential = app(CreateSponsorshipInvitationAuthority::class)->handle($alice, '09175180722', $maker, $checker);
    $bobVoucher = affiliationTestVoucher('SPON-BOB2');
    app(LinkSponsorshipAuthorityToVoucher::class)->handle($bobVoucher, $bobCredential);
    app(ActivateVoucherSponsorship::class)->handle($bobVoucher, $bob, affiliationClaimEvidence($bob));

    $carolCredential = app(CreateSponsorshipInvitationAuthority::class)->handle($bob, '09171234567', $maker, $checker);
    $carolVoucher = affiliationTestVoucher('SPON-CARL');
    app(LinkSponsorshipAuthorityToVoucher::class)->handle($carolVoucher, $carolCredential);
    app(ActivateVoucherSponsorship::class)->handle($carolVoucher, $carol, affiliationClaimEvidence($carol));

    $aliceMembership = AffiliationMembership::query()->where('source_type', 'x-change.affiliation-root-adoption')->firstOrFail();
    $carolMembership = AffiliationMembership::query()->where('subject_reference', app(TreasuryPrincipalReferenceResolverContract::class)->resolve($carol))->firstOrFail();

    expect(AffiliationPath::query()
        ->where('ancestor_membership_id', $aliceMembership->getKey())
        ->where('descendant_membership_id', $carolMembership->getKey())
        ->where('depth', 2)
        ->exists())->toBeTrue()
        ->and(fn () => app(CreateSponsorshipInvitationAuthority::class)->handle(
            $carol,
            '09173011987',
            $maker,
            $checker,
        ))->toThrow(DomainException::class, 'no longer eligible');
});

it('rolls sponsorship activation back when the onboarding voucher settlement fails', function (): void {
    $alice = affiliationTestUser('Alice Sponsor', 'alice-rollback@example.test', '09173011987');
    $maker = affiliationTestUser('Invitation Maker', 'maker-rollback@example.test', '09170000007');
    $checker = affiliationTestUser('Invitation Checker', 'checker-rollback@example.test', '09170000008');
    $bob = affiliationTestUser('Bob Recipient', 'bob-live-path@example.test', '09175180722');
    $alice->wallet()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform Wallet']);

    app(EnrollAffiliationRootAccount::class)->handle($alice, 'commissioning:root:alice:v1');
    $credential = app(CreateSponsorshipInvitationAuthority::class)->handle($alice, '09175180722', $maker, $checker);
    $voucher = affiliationTestVoucher('SPON-RBCK');
    app(LinkSponsorshipAuthorityToVoucher::class)->handle($voucher, $credential);

    $provisioner = Mockery::mock(ContactUserProvisionerContract::class);
    $provisioner->shouldReceive('provision')->once()->andReturn(new ContactPromotionResultData(
        promoted: true,
        user: $bob,
        meta: [
            'reused' => false,
            'principal_reference' => 'principal:account:bob-rollback',
            'position_count' => 2,
        ],
    ));
    $redemption = Mockery::mock(DefaultExecutionDriver::class);
    $redemption->shouldReceive('execute')
        ->once()
        ->andReturn(ExecutionResultData::failed('default', 'compatibility_redemption_rejected'));
    $driver = new OnboardingAccountProvisioningExecutionDriver(
        new PromoteContactToUser($provisioner),
        $redemption,
        app(DispatchVoucherClaimOutcome::class),
        app(DeferOnboardingFundingUntilAgreement::class),
        app(OnboardingVoucherClaimantAuthenticator::class),
        app(RefreshFundingLiquidity::class),
        app(ActivateVoucherSponsorship::class),
        Request::create('/x/claim/SPON-RBCK'),
    );

    $result = $driver->execute(affiliationOnboardingContext($voucher, '09175180722'));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('compatibility_redemption_rejected')
        ->and(AffiliationMembership::query()->count())->toBe(1)
        ->and(AffiliationSponsorship::query()->count())->toBe(0)
        ->and($credential->offer->refresh()->status)->toBe(ProvisioningRequestStatus::Offered)
        ->and($credential->offer->acceptance()->exists())->toBeFalse();
});

it('establishes sponsorship through the live onboarding execution driver', function (): void {
    $alice = affiliationTestUser('Alice Sponsor', 'alice-live-path@example.test', '09173011987');
    $maker = affiliationTestUser('Invitation Maker', 'maker-live-path@example.test', '09170000009');
    $checker = affiliationTestUser('Invitation Checker', 'checker-live-path@example.test', '09170000010');
    $bob = affiliationTestUser('Bob Recipient', 'bob-live-path@example.test', '09175180722');
    $alice->wallet()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform Wallet']);

    app(EnrollAffiliationRootAccount::class)->handle($alice, 'commissioning:root:alice:v1');
    $credential = app(CreateSponsorshipInvitationAuthority::class)->handle($alice, '09175180722', $maker, $checker);
    $voucher = affiliationTestVoucher('SPON-LIVE');
    app(LinkSponsorshipAuthorityToVoucher::class)->handle($voucher, $credential);

    $provisioner = Mockery::mock(ContactUserProvisionerContract::class);
    $provisioner->shouldReceive('provision')->once()->andReturn(new ContactPromotionResultData(
        promoted: true,
        user: $bob,
        meta: [
            'reused' => false,
            'principal_reference' => 'principal:account:bob-live-path',
            'position_count' => 2,
        ],
    ));
    $redemption = Mockery::mock(DefaultExecutionDriver::class);
    $redemption->shouldReceive('execute')->once()->andReturn(ExecutionResultData::succeeded('default'));
    $driver = new OnboardingAccountProvisioningExecutionDriver(
        new PromoteContactToUser($provisioner),
        $redemption,
        app(DispatchVoucherClaimOutcome::class),
        app(DeferOnboardingFundingUntilAgreement::class),
        app(OnboardingVoucherClaimantAuthenticator::class),
        app(RefreshFundingLiquidity::class),
        app(ActivateVoucherSponsorship::class),
        Request::create('/x/claim/SPON-LIVE'),
    );

    $result = $driver->execute(affiliationOnboardingContext($voucher, '09175180722'));

    expect($result->successful)->toBeTrue()
        ->and($result->events)->toContain('onboarding.affiliation_sponsorship_established')
        ->and($result->metadata['affiliation_authority_reference'])->toStartWith('x-provisioning:')
        ->and(AffiliationMembership::query()->count())->toBe(2)
        ->and(AffiliationSponsorship::query()->count())->toBe(1)
        ->and($credential->offer->refresh()->status)->toBe(ProvisioningRequestStatus::Activated);
});
