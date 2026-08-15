<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use DomainException;
use LBHurtado\XProvisioning\Contracts\ProvisioningEvidenceVerifierContract;
use LBHurtado\XProvisioning\Models\ProvisioningRevision;

final readonly class XChangeProvisioningEvidenceVerifier implements ProvisioningEvidenceVerifierContract
{
    public function assertVerified(ProvisioningRevision $revision, array $evidence): void
    {
        $required = (array) data_get(
            $revision->snapshot,
            'required_evidence',
            config("x-provisioning.profiles.{$revision->request->profile->value}.required_evidence", []),
        );

        $missing = collect($required)->filter(function (mixed $field) use ($evidence): bool {
            if (! is_string($field) || ! array_key_exists($field, $evidence)) {
                return true;
            }

            $value = $evidence[$field];

            if (in_array($field, ['otp', 'responsibility_attestation', 'kyc'], true)) {
                return $value !== true;
            }

            return ! is_string($value) || trim($value) === '';
        })->values()->all();

        if ($missing !== []) {
            throw new DomainException('Provisioning evidence is incomplete: '.implode(', ', $missing));
        }
    }
}
