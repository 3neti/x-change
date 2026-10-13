<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\HyperVerge\Data\KycEvidenceArtifact;

final class ContactKycEvidenceResolver
{
    /** @return list<KycEvidenceArtifact> */
    public function resolve(Contact $contact): array
    {
        if (! $contact->isKycApproved()) {
            return [];
        }

        return $contact->kycEvidence();
    }

    /** @return array{contents:string,mime_type:string,extension:string,size:int,sha256:string} */
    public function read(Contact $contact, string $mediaId): array
    {
        return $contact->readKycEvidence($mediaId);
    }
}
