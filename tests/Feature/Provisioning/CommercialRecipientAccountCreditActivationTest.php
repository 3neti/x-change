<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use LBHurtado\XChange\Actions\Provisioning\CreateCockpitProvisioningRequest;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Models\ProvisioningOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\CommercialRecipientDesignationAuthorityVerifier;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XProvisioning\Actions\AcceptProvisioningOffer;
use LBHurtado\XProvisioning\Actions\ActivateProvisioningAcceptance;
use LBHurtado\XProvisioning\Actions\ApproveProvisioningRequest;
use LBHurtado\XProvisioning\Actions\IssueProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

it('binds the accepted recipient Account and atomically switches governed economics', function (): void {
    enableNetbankTreasuryForTests();
    app(ProvisionCommercialBaselines::class)->provision('commissioning:recipient-account-credit');
    $maker = actingAsTestUser();
    $checker = commercialRecipientTransitionUser('Commercial Checker');
    $recipient = commercialRecipientTransitionUser('3neti Representative');
    fundTestUserWallet($recipient, 0);
    authorizeRecipientProvisioning($maker, ProvisioningOperatorCapability::Request);
    authorizeRecipientCommercial($checker, CommercialOperatorCapability::ApproveOfferings);
    $before = CommercialComponentEconomicsHead::query()
        ->with('currentActivation.economics.offering')
        ->get()
        ->mapWithKeys(fn ($head): array => [
            $head->profile => [
                'economics_id' => $head->currentActivation->economics->getKey(),
                'offering_id' => $head->currentActivation->economics->commercial_offering_id,
                'catalog' => $head->currentActivation->economics->offering->offering()->catalog->toArray(),
            ],
        ]);

    $request = app(CreateCockpitProvisioningRequest::class)->handle($maker, [
        'profile' => 'commercial_recipient_designation',
        'purpose' => 'Credit the accepted 3neti Account under the commissioning agreement.',
        'capabilities' => [],
    ]);
    $snapshot = $request->revisions()->sole()->snapshot;

    expect($snapshot)->toMatchArray([
        'settlement_designation_reference' => 'designation:commissioning:3neti:v2',
        'supersedes_designation_reference' => 'designation:commissioning:3neti:v1',
        'settlement_disposition' => 'internal_account_credit',
        'settlement_account_binding' => 'accepted_candidate_account',
    ])->and($snapshot['component_scope'])->not->toBeEmpty();

    app(ApproveProvisioningRequest::class)->handle($request, $checker);
    $credential = app(IssueProvisioningOffer::class)->handle($request);
    $offer = app(AcceptProvisioningOffer::class)->handle(
        claimToken: $credential->claimToken,
        candidateType: $recipient->getMorphClass(),
        candidateReference: (string) $recipient->getKey(),
        evidence: [
            'representative' => 'Verified 3neti representative',
            'authority' => 'Accepted Account-credit authority',
            'agreement' => 'Accepted commissioning agreement',
        ],
    );

    app(ActivateProvisioningAcceptance::class)->handle($offer, $checker);

    $designation = CommercialRecipientDesignation::query()
        ->where('designation_reference', 'designation:commissioning:3neti:v2')
        ->sole();
    $wallet = $recipient->wallet()->where('slug', 'platform')->sole();
    $principalReference = app(TreasuryPrincipalReferenceResolverContract::class)->resolve($recipient);
    app(CommercialRecipientDesignationAuthorityVerifier::class)->assertValid($designation);

    expect($designation->settlement_account_reference)->toBe('wallet:'.$wallet->uuid)
        ->and($designation->settlement_principal_reference)->toBe($principalReference)
        ->and($designation->representative_reference)->toBe((string) $recipient->getKey())
        ->and(CommercialRecipientDesignation::query()
            ->where('designation_reference', 'designation:commissioning:3neti:v1')
            ->whereNull('revoked_at')
            ->exists())->toBeTrue();

    $after = CommercialComponentEconomicsHead::query()
        ->with('currentActivation.economics.offering')
        ->get();

    foreach ($after as $head) {
        $economics = $head->currentActivation->economics;
        expect($economics->getKey())->not->toBe($before[$head->profile]['economics_id'])
            ->and($economics->version)->toBe(2)
            ->and($economics->commercial_offering_id)->toBe($before[$head->profile]['offering_id'])
            ->and($economics->offering->offering()->catalog->toArray())->toBe($before[$head->profile]['catalog']);

        foreach ($economics->economics()->components as $component) {
            foreach ($component->allocationSchedule?->rules ?? [] as $rule) {
                expect($rule->designationReference)->not->toBe('designation:commissioning:3neti:v1');
            }
        }
    }
});

it('rolls back designation and economics when the activation checker lacks commercial approval', function (): void {
    enableNetbankTreasuryForTests();
    app(ProvisionCommercialBaselines::class)->provision('commissioning:recipient-account-credit-denied');
    $maker = actingAsTestUser();
    $checker = commercialRecipientTransitionUser('Provisioning-only Checker');
    $recipient = commercialRecipientTransitionUser('3neti Representative');
    fundTestUserWallet($recipient, 0);
    authorizeRecipientProvisioning($maker, ProvisioningOperatorCapability::Request);
    $beforeHeadIds = CommercialComponentEconomicsHead::query()->pluck('current_activation_id', 'profile')->all();

    $request = app(CreateCockpitProvisioningRequest::class)->handle($maker, [
        'profile' => 'commercial_recipient_designation',
        'purpose' => 'Attempt an unauthorized economics switch.',
        'capabilities' => [],
    ]);
    app(ApproveProvisioningRequest::class)->handle($request, $checker);
    $credential = app(IssueProvisioningOffer::class)->handle($request);
    $offer = app(AcceptProvisioningOffer::class)->handle(
        claimToken: $credential->claimToken,
        candidateType: $recipient->getMorphClass(),
        candidateReference: (string) $recipient->getKey(),
        evidence: ['representative' => 'verified', 'authority' => 'accepted', 'agreement' => 'accepted'],
    );

    expect(fn () => app(ActivateProvisioningAcceptance::class)->handle($offer, $checker))
        ->toThrow(AuthorizationException::class, 'commercial.offerings.approve');

    expect(CommercialRecipientDesignation::query()
        ->where('designation_reference', 'designation:commissioning:3neti:v2')
        ->exists())->toBeFalse()
        ->and(CommercialComponentEconomicsHead::query()->pluck('current_activation_id', 'profile')->all())
        ->toBe($beforeHeadIds)
        ->and(ProvisioningOffer::query()->findOrFail($offer->getKey())->status->value)
        ->toBe('activation_pending');
});

it('exposes the recipient transition as a first-class Cockpit provisioning profile', function (): void {
    enableNetbankTreasuryForTests();
    app(ProvisionCommercialBaselines::class)->provision('commissioning:recipient-cockpit');
    $maker = actingAsTestUser();
    authorizeRecipientProvisioning($maker, ProvisioningOperatorCapability::Request);

    $this->post(route('x-change.cockpit.provisioning.requests.store'), [
        'profile' => 'commercial_recipient_designation',
        'purpose' => 'Invite the governed commercial recipient.',
        'capabilities' => [],
    ])->assertRedirect();

    $request = ProvisioningRequest::query()->sole();
    $snapshot = $request->revisions()->sole()->snapshot;

    expect($request->profile->value)->toBe('commercial_recipient_designation')
        ->and($snapshot['activation_gate'])->toBe('recipient_acceptance_and_economics_switch')
        ->and($snapshot['settlement_account_binding'])->toBe('accepted_candidate_account')
        ->and($snapshot['settlement_account_reference'])->toBeNull()
        ->and($snapshot['component_scope'])->not->toBeEmpty();
});

function commercialRecipientTransitionUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name).'-'.Str::uuid().'@example.test',
        'password' => 'password',
    ]);
}

function authorizeRecipientProvisioning(User $operator, ProvisioningOperatorCapability $capability): void
{
    ProvisioningOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => $capability->value,
        'authorization_reference' => 'test:recipient-provisioning:'.$operator->getKey().':'.$capability->value,
        'valid_from' => now()->subMinute(),
    ]);
}

function authorizeRecipientCommercial(User $operator, CommercialOperatorCapability $capability): void
{
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => $capability->value,
        'authorization_reference' => 'test:recipient-commercial:'.$operator->getKey().':'.$capability->value,
        'valid_from' => now()->subMinute(),
    ]);
}
