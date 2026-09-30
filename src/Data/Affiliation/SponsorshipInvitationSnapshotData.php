<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Affiliation;

use DomainException;
use LBHurtado\XAffiliation\Enums\AffiliationRelationshipType;

final readonly class SponsorshipInvitationSnapshotData
{
    public const string Schema = 'x-change.affiliation-sponsorship-invitation.v1';

    public const string ActivationGate = 'affiliation_sponsorship';

    public function __construct(
        public string $networkReference,
        public string $sponsorSubjectType,
        public string $sponsorSubjectReference,
        public string $recipientMobileKey,
        public AffiliationRelationshipType $relationshipType = AffiliationRelationshipType::OnboardingSponsor,
    ) {
        foreach ([
            $this->networkReference,
            $this->sponsorSubjectType,
            $this->sponsorSubjectReference,
            $this->recipientMobileKey,
        ] as $value) {
            if (trim($value) === '') {
                throw new DomainException('Sponsorship invitation references must be non-empty.');
            }
        }

        if (preg_match('/^[a-f0-9]{64}$/', $this->recipientMobileKey) !== 1) {
            throw new DomainException('The recipient mobile identity key must be a lowercase SHA-256 HMAC.');
        }
    }

    /** @param array<string, mixed> $snapshot */
    public static function fromArray(array $snapshot): self
    {
        if ((string) data_get($snapshot, 'schema') !== self::Schema
            || (string) data_get($snapshot, 'activation_gate') !== self::ActivationGate) {
            throw new DomainException('The sponsorship invitation snapshot schema is invalid.');
        }

        $relationship = AffiliationRelationshipType::tryFrom(
            (string) data_get($snapshot, 'relationship_type'),
        );

        if (! $relationship instanceof AffiliationRelationshipType) {
            throw new DomainException('The sponsorship relationship type is invalid.');
        }

        return new self(
            networkReference: (string) data_get($snapshot, 'network_reference'),
            sponsorSubjectType: (string) data_get($snapshot, 'sponsor.subject_type'),
            sponsorSubjectReference: (string) data_get($snapshot, 'sponsor.subject_reference'),
            recipientMobileKey: (string) data_get($snapshot, 'recipient.mobile_identity_key'),
            relationshipType: $relationship,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => self::Schema,
            'activation_gate' => self::ActivationGate,
            'network_reference' => $this->networkReference,
            'sponsor' => [
                'subject_type' => $this->sponsorSubjectType,
                'subject_reference' => $this->sponsorSubjectReference,
            ],
            'recipient' => [
                'identity_type' => 'mobile',
                'mobile_identity_key' => $this->recipientMobileKey,
            ],
            'relationship_type' => $this->relationshipType->value,
            'required_evidence' => ['name', 'email', 'mobile', 'otp'],
        ];
    }
}
