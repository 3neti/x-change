<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XProvisioning\Actions\AcceptProvisioningOffer;
use LBHurtado\XProvisioning\Actions\ActivateProvisioningAcceptance;
use LBHurtado\XProvisioning\Actions\ApproveProvisioningRequest;
use LBHurtado\XProvisioning\Actions\CreateProvisioningRequest;
use LBHurtado\XProvisioning\Actions\IssueProvisioningOffer;
use LBHurtado\XProvisioning\Actions\RevokeProvisioningAcceptance;
use LBHurtado\XProvisioning\Actions\SubmitProvisioningRequest;
use LBHurtado\XProvisioning\Enums\ProvisioningActivationMode;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;

it('projects and revokes independently approved commercial recipient designation authority', function (): void {
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create([
        'name' => 'Designation Checker',
        'email' => 'designation-checker@example.test',
        'password' => 'password',
    ]);
    $representative = User::query()->create([
        'name' => 'Service Provider Representative',
        'email' => 'service-provider@example.test',
        'password' => 'password',
    ]);
    $request = app(CreateProvisioningRequest::class)->handle(
        profile: ProvisioningProfile::CommercialRecipientDesignation,
        snapshot: [
            'counterparty_reference' => 'counterparty:hyperverge',
            'commercial_role' => 'kyc_service_provider',
            'component_scope' => ['inputs.fields.kyc'],
            'agreement_reference' => 'agreement:institution-hyperverge:v1',
            'settlement_designation_reference' => 'designation:hyperverge:kyc:v1',
            'settlement_disposition' => 'internal_account_credit',
            'settlement_account_reference' => 'account:hyperverge:php',
            'settlement_principal_reference' => 'principal:hyperverge',
            'tax_profile_reference' => 'tax-profile:hyperverge:ph:v1',
            'effective_from' => '2026-01-01T00:00:00+00:00',
        ],
        maker: $maker,
        activationMode: ProvisioningActivationMode::ReviewRequired,
    );
    app(SubmitProvisioningRequest::class)->handle($request, $maker);
    app(ApproveProvisioningRequest::class)->handle($request, $checker);
    $credential = app(IssueProvisioningOffer::class)->handle($request);
    $offer = app(AcceptProvisioningOffer::class)->handle(
        claimToken: $credential->claimToken,
        candidateType: $representative->getMorphClass(),
        candidateReference: (string) $representative->getKey(),
        evidence: [
            'representative' => 'Verified service-provider representative',
            'authority' => 'Board-authorized service agreement',
            'agreement' => 'Executed agreement:institution-hyperverge:v1',
        ],
    );

    app(ActivateProvisioningAcceptance::class)->handle($offer, $checker);

    $designation = CommercialRecipientDesignation::query()
        ->where('designation_reference', 'designation:hyperverge:kyc:v1')
        ->sole();

    expect($designation->counterparty_reference)->toBe('counterparty:hyperverge')
        ->and($designation->component_scope)->toBe(['inputs.fields.kyc'])
        ->and($designation->settlement_disposition)->toBe('internal_account_credit')
        ->and($designation->settlement_account_reference)->toBe('account:hyperverge:php')
        ->and($designation->settlement_principal_reference)->toBe('principal:hyperverge')
        ->and($designation->representative_reference)->toBe((string) $representative->getKey())
        ->and((string) $designation->activated_by_id)->toBe((string) $checker->getKey())
        ->and($designation->revoked_at)->toBeNull();

    app(RevokeProvisioningAcceptance::class)->handle(
        $offer->refresh(),
        $checker,
        'The governed service agreement ended.',
    );

    expect($designation->refresh()->revoked_at)->not->toBeNull()
        ->and($designation->revocation_reference)->toContain(':revoked:')
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.recipient_designation.activated')
            ->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.recipient_designation.revoked')
            ->count())->toBe(1);
});
