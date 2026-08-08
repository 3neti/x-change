<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartner;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartnerDestination;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerDestinationData;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerRevisionData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function authorizePartnerOperator(User $operator, CommercialOperatorCapability $capability): void
{
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => $capability->value,
        'authorization_reference' => 'board:commercial-partners',
        'valid_from' => now()->subMinute(),
    ]);
}

function configurePartnerSystemPrincipal(): User
{
    $system = actingAsTestUser();
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);

    return $system;
}

function partnerRevisionInput(string $reference = 'partner:governance'): CommercialPartnerRevisionData
{
    return new CommercialPartnerRevisionData(
        reference: $reference,
        displayName: 'Governed Partner',
        legalName: 'Governed Partner Incorporated',
        externalReference: 'crm:partner-100',
        attributionBasis: 'contractual_referral',
        authorizationReference: 'contract:partner-100',
        terms: ['commission_basis' => 'fixed', 'settlement_cycle' => 'monthly'],
    );
}

it('activates a Commercial Partner through maker-checker governance', function (): void {
    configurePartnerSystemPrincipal();
    $maker = actingAsTestUser();
    $checker = actingAsTestUser();
    authorizePartnerOperator($maker, CommercialOperatorCapability::ManagePartners);
    authorizePartnerOperator($checker, CommercialOperatorCapability::ApprovePartners);

    $action = app(ManageCommercialPartner::class);
    $draft = $action->createDraft($maker, partnerRevisionInput());
    $submitted = $action->submit($maker, $draft);
    $approved = $action->approve($checker, $submitted);

    expect($approved->status)->toBe(CommercialPartnerRevisionStatus::Approved)
        ->and($approved->partner->status)->toBe(CommercialPartnerStatus::Active)
        ->and($approved->checker_id)->toBe($checker->getKey())
        ->and(ExecutionJournalEntry::query()
            ->whereIn('event_type', [
                'commercial.partner.drafted',
                'commercial.partner.submitted',
                'commercial.partner.approved',
            ])->count())->toBe(3);
});

it('requires an independent checker and excludes the system principal', function (): void {
    $system = configurePartnerSystemPrincipal();
    $maker = actingAsTestUser();
    authorizePartnerOperator($maker, CommercialOperatorCapability::ManagePartners);
    authorizePartnerOperator($maker, CommercialOperatorCapability::ApprovePartners);
    authorizePartnerOperator($system, CommercialOperatorCapability::ManagePartners);

    $action = app(ManageCommercialPartner::class);
    $submitted = $action->submit($maker, $action->createDraft($maker, partnerRevisionInput('partner:separation')));

    expect(fn () => $action->approve($maker, $submitted))
        ->toThrow(DomainException::class, 'checker must be different')
        ->and(fn () => $action->createDraft($system, partnerRevisionInput('partner:system')))
        ->toThrow(AuthorizationException::class);
});

it('approves only a validated encrypted destination for an active partner', function (): void {
    configurePartnerSystemPrincipal();
    $maker = actingAsTestUser();
    $checker = actingAsTestUser();
    authorizePartnerOperator($maker, CommercialOperatorCapability::ManagePartners);
    authorizePartnerOperator($checker, CommercialOperatorCapability::ApprovePartners);

    $partners = app(ManageCommercialPartner::class);
    $partnerRevision = $partners->approve(
        $checker,
        $partners->submit($maker, $partners->createDraft($maker, partnerRevisionInput('partner:destination'))),
    );
    $destinations = app(ManageCommercialPartnerDestination::class);
    $draft = $destinations->createDraft(
        $maker,
        $partnerRevision->partner,
        new CommercialPartnerDestinationData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            currency: 'PHP',
            bankCode: 'GXCHPHM2XXX',
            accountNumber: '09171234567',
            recipientName: 'Governed Partner',
            mobile: '09171234567',
            authorizationReference: 'board:partner-destination',
        ),
    );
    $approved = $destinations->approve($checker, $destinations->submit($maker, $draft));

    expect($approved)->toBeInstanceOf(CommercialPartnerDestinationRevision::class)
        ->and($approved->status)->toBe(CommercialPartnerRevisionStatus::Approved)
        ->and($approved->destination_summary)->toBe('GXCHPHM2XXX · ••••4567')
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.partner_destination.approved')->exists())->toBeTrue();
});
