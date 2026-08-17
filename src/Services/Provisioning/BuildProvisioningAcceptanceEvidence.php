<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Models\ProvisioningRevision;

final readonly class BuildProvisioningAcceptanceEvidence
{
    /** @return array<string, mixed> */
    public function build(
        ProvisioningRevision $revision,
        Model $candidate,
        bool $responsibilityAttestation,
    ): array {
        $mobile = $candidate instanceof HasMobileChannel
            ? $candidate->getMobileChannel()
            : $candidate->getAttribute('mobile');
        $mobileVerified = ! array_key_exists('mobile_verified_at', $candidate->getAttributes())
            || $candidate->getAttribute('mobile_verified_at') !== null;
        $evidence = [
            'name' => (string) $candidate->getAttribute('name'),
            'email' => (string) $candidate->getAttribute('email'),
            'mobile' => (string) $mobile,
            'otp' => $mobileVerified,
            'responsibility_attestation' => $responsibilityAttestation,
        ];

        if ($revision->request->profile !== ProvisioningProfile::CommercialRecipientDesignation) {
            return $evidence;
        }

        return [
            ...$evidence,
            'representative' => (string) $candidate->getAttribute('name'),
            'authority' => 'authenticated_candidate_responsibility_attestation',
            'agreement' => (string) data_get($revision->snapshot, 'agreement_reference', ''),
        ];
    }
}
