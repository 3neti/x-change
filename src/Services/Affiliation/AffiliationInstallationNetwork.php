<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Affiliation;

use DomainException;
use LBHurtado\XAffiliation\Actions\EnsureApplicationInstanceNetwork;
use LBHurtado\XAffiliation\Models\AffiliationNetwork;

final readonly class AffiliationInstallationNetwork
{
    public function __construct(
        private EnsureApplicationInstanceNetwork $ensureNetwork,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('x-change.affiliation.enabled', false);
    }

    public function ensure(): ?AffiliationNetwork
    {
        if (! $this->enabled()) {
            return null;
        }

        $instanceId = trim((string) config('x-change.instance.id'));
        $identityPepper = trim((string) config('x-affiliation.identity_pepper'));

        if ($instanceId === '') {
            throw new DomainException(
                'XCHANGE_INSTANCE_ID is required when affiliation networking is enabled.',
            );
        }

        if ($identityPepper === '') {
            throw new DomainException(
                'X_AFFILIATION_IDENTITY_PEPPER is required when affiliation networking is enabled.',
            );
        }

        return $this->ensureNetwork->handle(
            installationReference: $instanceId,
            metadata: [
                'schema' => 'x-change.affiliation-network.v1',
                'installation_reference_hash' => hash('sha256', $instanceId),
            ],
        );
    }
}
