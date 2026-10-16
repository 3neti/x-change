<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Contracts\Commercial\PublicIssuanceEscrowPrincipalResolverContract;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class ConfiguredPublicIssuanceEscrowPrincipalResolver implements PublicIssuanceEscrowPrincipalResolverContract
{
    public function resolve(): CommercialPrincipal
    {
        if (! (bool) config('x-change.public_issuance_escrow.enabled', false)) {
            throw new TreasuryConfigurationException(
                'Public issuance requires the dedicated escrow principal to be enabled.',
            );
        }

        $reference = trim((string) config('x-change.public_issuance_escrow.principal.reference'));

        if ($reference === '') {
            throw new TreasuryConfigurationException(
                'The public issuance escrow principal reference is missing.',
            );
        }

        $principals = CommercialPrincipal::query()
            ->where('reference', $reference)
            ->where('active', true)
            ->limit(2)
            ->get();

        if ($principals->count() !== 1) {
            throw new TreasuryConfigurationException(
                'Public issuance requires exactly one active escrow principal.',
            );
        }

        $principal = $principals->first();

        if (! $principal instanceof CommercialPrincipal) {
            throw new TreasuryConfigurationException(
                'The configured public issuance escrow principal is unavailable.',
            );
        }

        $expectedLegalName = trim((string) config('x-change.public_issuance_escrow.principal.legal_name'));
        $expectedAuthorizationReference = trim((string) config('x-change.public_issuance_escrow.principal.authorization_reference'));

        if ($expectedLegalName === '' || $expectedAuthorizationReference === '') {
            throw new TreasuryConfigurationException(
                'Public issuance escrow legal name and authorization reference are required.',
            );
        }

        if ($principal->legal_name !== $expectedLegalName
            || $principal->authorization_reference !== $expectedAuthorizationReference) {
            throw new TreasuryConfigurationException(
                'The public issuance escrow principal does not match its commissioned authority.',
            );
        }

        return $principal;
    }
}
