<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Affiliation;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XAffiliation\Actions\CheckSponsorshipEligibility;
use LBHurtado\XAffiliation\Contracts\AffiliationIdentityKeyFactoryContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Affiliation\SponsorshipInvitationSnapshotData;
use LBHurtado\XChange\Services\Affiliation\AffiliationInstallationNetwork;
use LBHurtado\XProvisioning\Actions\ApproveProvisioningRequest;
use LBHurtado\XProvisioning\Actions\CreateProvisioningRequest;
use LBHurtado\XProvisioning\Actions\IssueProvisioningOffer;
use LBHurtado\XProvisioning\Actions\SubmitProvisioningRequest;
use LBHurtado\XProvisioning\Data\ProvisioningOfferCredentialData;
use LBHurtado\XProvisioning\Enums\ProvisioningActivationMode;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use Throwable;

final readonly class CreateSponsorshipInvitationAuthority
{
    public function __construct(
        private AffiliationInstallationNetwork $networks,
        private AffiliationIdentityKeyFactoryContract $identityKeys,
        private CheckSponsorshipEligibility $eligibility,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private CreateProvisioningRequest $createRequest,
        private SubmitProvisioningRequest $submitRequest,
        private ApproveProvisioningRequest $approveRequest,
        private IssueProvisioningOffer $issueOffer,
    ) {}

    public function handle(
        Model $sponsor,
        string $recipientMobile,
        Model $maker,
        Model $checker,
    ): ProvisioningOfferCredentialData {
        return DB::transaction(function () use ($sponsor, $recipientMobile, $maker, $checker): ProvisioningOfferCredentialData {
            $network = $this->networks->ensure();

            if ($network === null) {
                throw new DomainException('Affiliation networking is disabled.');
            }

            try {
                $canonicalMobile = phone($recipientMobile, 'PH')->formatE164();
            } catch (Throwable $exception) {
                throw new DomainException('A valid targeted Philippine mobile is required.', previous: $exception);
            }

            $sponsorReference = $this->principalReferences->resolve($sponsor);
            $mobileKey = $this->identityKeys->forMobile($network->reference, $canonicalMobile);
            $eligibility = $this->eligibility->handle(
                network: $network,
                sponsorSubjectType: 'account',
                sponsorSubjectReference: $sponsorReference,
                candidateMobileKey: $mobileKey,
            );

            if (! $eligibility->isEligible()) {
                throw new DomainException('This onboarding invitation is no longer eligible for use.');
            }

            $snapshot = new SponsorshipInvitationSnapshotData(
                networkReference: $network->reference,
                sponsorSubjectType: 'account',
                sponsorSubjectReference: $sponsorReference,
                recipientMobileKey: $mobileKey,
            );
            $request = $this->createRequest->handle(
                profile: ProvisioningProfile::AccountInvitation,
                snapshot: $snapshot->toArray(),
                maker: $maker,
                activationMode: ProvisioningActivationMode::ActivateOnVerifiedClaim,
                subjectType: 'affiliation_mobile_identity',
                subjectReference: $mobileKey,
                metadata: [
                    'schema' => SponsorshipInvitationSnapshotData::Schema,
                    'contains_raw_mobile' => false,
                ],
            );
            $this->submitRequest->handle($request, $maker);
            $this->approveRequest->handle($request, $checker);

            return $this->issueOffer->handle($request->refresh());
        }, attempts: 3);
    }
}
