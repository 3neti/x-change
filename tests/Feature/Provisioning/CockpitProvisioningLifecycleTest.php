<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Models\ProvisioningOperatorAuthorization;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XProvisioning\Actions\ApproveProvisioningRequest;
use LBHurtado\XProvisioning\Actions\CreateProvisioningRequest;
use LBHurtado\XProvisioning\Actions\IssueProvisioningOffer;
use LBHurtado\XProvisioning\Actions\SubmitProvisioningRequest;
use LBHurtado\XProvisioning\Enums\ProvisioningActivationMode;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Enums\ProvisioningRequestStatus;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningSeat;

function authorizeProvisioningOperator(User $operator, ProvisioningOperatorCapability ...$capabilities): void
{
    foreach ($capabilities as $capability) {
        ProvisioningOperatorAuthorization::query()->create([
            'operator_type' => $operator->getMorphClass(),
            'operator_id' => $operator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'provisioning-test:'.$operator->getKey().':'.$capability->value,
            'valid_from' => now()->subMinute(),
        ]);
    }
}

it('conceals the workspace and exposes a sanitized seat and request read model to authorized operators', function (): void {
    enableNetbankTreasuryForTests();
    $operator = actingAsTestUser();

    $this->get(route('x-change.cockpit.provisioning.index'))->assertNotFound();

    authorizeProvisioningOperator($operator, ProvisioningOperatorCapability::View);
    $this->artisan('x-change:provisioning:commission')->assertSuccessful();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.provisioning.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Provisioning')
        ->assertJsonPath('props.provisioning.schema', 'x-change.cockpit.provisioning.v1')
        ->assertJsonPath('props.provisioning.stats.vacant_seats', ProvisioningSeat::query()->count())
        ->assertJsonPath('props.xchange.navigation.provisioning_controls_visible', true);
});

it('runs request approval offer and verified acceptance without granting domain authority', function (): void {
    enableNetbankTreasuryForTests();
    actingAsTestUser();
    $maker = auth()->user();
    $checker = User::query()->create([
        'name' => 'Provisioning Checker',
        'email' => 'provisioning-checker@example.test',
        'password' => 'password',
    ]);
    $candidate = User::query()->create([
        'name' => 'Treasury Candidate',
        'email' => 'treasury-candidate@example.test',
        'password' => 'password',
    ]);
    $candidate->setMobileChannel('639171234599')->forceFill(['mobile_verified_at' => now()])->save();
    authorizeProvisioningOperator(
        $maker,
        ProvisioningOperatorCapability::View,
        ProvisioningOperatorCapability::Request,
        ProvisioningOperatorCapability::Issue,
    );
    authorizeProvisioningOperator(
        $checker,
        ProvisioningOperatorCapability::View,
        ProvisioningOperatorCapability::Approve,
    );
    $this->artisan('x-change:provisioning:commission')->assertSuccessful();
    $seat = ProvisioningSeat::query()->where('profile', 'treasury_maker')->sole();

    $this->post(route('x-change.cockpit.provisioning.requests.store'), [
        'seat_reference' => $seat->reference,
        'purpose' => 'Prepare a named Treasury maker for testing.',
    ])->assertRedirect();

    $provisioning = ProvisioningRequest::query()->sole();
    expect($provisioning->status)->toBe(ProvisioningRequestStatus::AwaitingApproval)
        ->and($provisioning->commissioning)->toBeTrue()
        ->and($seat->refresh()->request_id)->toBe($provisioning->getKey());

    $this->actingAs($checker)->post(
        route('x-change.cockpit.provisioning.requests.approvals.store', $provisioning),
        ['confirm_snapshot' => true],
    )->assertRedirect();

    $issued = $this->actingAs($maker)->postJson(
        route('x-change.cockpit.provisioning.requests.offers.store', $provisioning),
        ['acknowledge_one_time_link' => true],
    );
    $issued->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('secret_display', 'one_time_only');

    $offer = ProvisioningOffer::query()->sole();
    $claimToken = basename((string) $issued->json('claim_url'));

    $this->actingAs($candidate)->post(
        route('x-change.provisioning.claim.accept', ['token' => $claimToken]),
        ['responsibility_attestation' => true],
    )->assertRedirect();

    expect($offer->refresh()->status)->toBe(ProvisioningRequestStatus::ActivationPending)
        ->and(TreasuryOperatorAuthorization::query()->where('operator_id', $candidate->getKey())->exists())->toBeFalse();
});

it('rejects incomplete server-verified evidence', function (): void {
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create(['name' => 'Checker', 'email' => 'checker2@example.test', 'password' => 'password']);
    $candidate = User::query()->create([
        'name' => 'Candidate',
        'email' => 'candidate2@example.test',
        'password' => 'password',
    ]);
    $candidate->setMobileChannel('639171234598')->forceFill(['mobile_verified_at' => null])->save();
    $provisioning = app(CreateProvisioningRequest::class)->handle(
        profile: ProvisioningProfile::CommercialMaker,
        snapshot: ['purpose' => 'Evidence check'],
        maker: $maker,
        activationMode: ProvisioningActivationMode::ReviewRequired,
    );
    app(SubmitProvisioningRequest::class)->handle($provisioning, $maker);
    app(ApproveProvisioningRequest::class)->handle($provisioning, $checker);
    $credential = app(IssueProvisioningOffer::class)->handle($provisioning);

    $this->actingAs($candidate)->post(
        route('x-change.provisioning.claim.accept', ['token' => $credential->claimToken]),
        ['responsibility_attestation' => true],
    )->assertSessionHasErrors();

    expect($credential->offer->refresh()->status)->toBe(ProvisioningRequestStatus::Offered);
});

it('records governed rejection and maker withdrawal through Cockpit', function (): void {
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create(['name' => 'Checker', 'email' => 'terminal-checker@example.test', 'password' => 'password']);
    authorizeProvisioningOperator($maker, ProvisioningOperatorCapability::View, ProvisioningOperatorCapability::Request);
    authorizeProvisioningOperator($checker, ProvisioningOperatorCapability::View, ProvisioningOperatorCapability::Approve);

    $rejected = app(CreateProvisioningRequest::class)->handle(
        ProvisioningProfile::CommercialMaker,
        ['purpose' => 'Reject this request'],
        $maker,
        activationMode: ProvisioningActivationMode::ReviewRequired,
    );
    app(SubmitProvisioningRequest::class)->handle($rejected, $maker);

    $this->actingAs($checker)->post(
        route('x-change.cockpit.provisioning.requests.rejections.store', $rejected),
        ['reason' => 'The approved responsibility scope is incomplete.'],
    )->assertRedirect();

    $withdrawn = app(CreateProvisioningRequest::class)->handle(
        ProvisioningProfile::CommercialChecker,
        ['purpose' => 'Withdraw this request'],
        $maker,
        activationMode: ProvisioningActivationMode::ReviewRequired,
    );
    app(SubmitProvisioningRequest::class)->handle($withdrawn, $maker);

    $this->actingAs($maker)->post(
        route('x-change.cockpit.provisioning.requests.withdrawals.store', $withdrawn),
        ['reason' => 'This commissioning seat is no longer required.'],
    )->assertRedirect();

    expect($rejected->refresh()->status)->toBe(ProvisioningRequestStatus::Rejected)
        ->and($withdrawn->refresh()->status)->toBe(ProvisioningRequestStatus::Withdrawn);
});

it('expires elapsed invitation offers idempotently without provider calls', function (): void {
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create(['name' => 'Checker', 'email' => 'expiry-checker@example.test', 'password' => 'password']);
    $request = app(CreateProvisioningRequest::class)->handle(
        ProvisioningProfile::TreasuryMaker,
        ['purpose' => 'Expired offer test'],
        $maker,
        activationMode: ProvisioningActivationMode::ReviewRequired,
    );
    app(SubmitProvisioningRequest::class)->handle($request, $maker);
    app(ApproveProvisioningRequest::class)->handle($request, $checker);
    $credential = app(IssueProvisioningOffer::class)->handle($request, now()->subSecond());

    $this->artisan('x-change:provisioning:expire-offers')->assertSuccessful();
    $this->artisan('x-change:provisioning:expire-offers')->assertSuccessful();

    expect($credential->offer->refresh()->status)->toBe(ProvisioningRequestStatus::Expired)
        ->and($request->refresh()->status)->toBe(ProvisioningRequestStatus::Expired);
});
