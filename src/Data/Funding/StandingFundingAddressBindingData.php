<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\StandingFundingAddressBindingRevision;

final readonly class StandingFundingAddressBindingData
{
    /**
     * @param  array<string, mixed>|null  $destinationSnapshot
     */
    public function __construct(
        public string $accountReference,
        public string $bindingKey,
        public ?array $destinationSnapshot,
        public ?string $destinationFingerprint,
        public ?int $revisionId,
        public ?string $revisionReference,
        public int $version,
        public CarbonImmutable $effectiveAt,
    ) {}

    public static function fromRevision(
        StandingFundingAddressBindingRevision $revision,
        ?CarbonImmutable $effectiveAt = null,
    ): self {
        return new self(
            accountReference: $revision->account_reference_ciphertext,
            bindingKey: $revision->binding_key,
            destinationSnapshot: $revision->destination_snapshot_ciphertext,
            destinationFingerprint: $revision->destination_fingerprint,
            revisionId: $revision->getKey(),
            revisionReference: $revision->reference,
            version: $revision->binding_version,
            effectiveAt: $effectiveAt ?? $revision->effective_at,
        );
    }
}
