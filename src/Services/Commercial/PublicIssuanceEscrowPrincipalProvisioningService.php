<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Commercial\CommercialPrincipalProvisioningData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class PublicIssuanceEscrowPrincipalProvisioningService
{
    public function __construct(
        private WalletProvisioningContract $accounts,
    ) {}

    public function inspect(): CommercialPrincipalProvisioningData
    {
        return $this->resolve(commit: false);
    }

    public function provision(): CommercialPrincipalProvisioningData
    {
        return $this->resolve(commit: true);
    }

    private function resolve(bool $commit): CommercialPrincipalProvisioningData
    {
        $reference = trim((string) config('x-change.public_issuance_escrow.principal.reference'));
        $legalName = trim((string) config('x-change.public_issuance_escrow.principal.legal_name'));
        $authorizationReference = trim((string) config(
            'x-change.public_issuance_escrow.principal.authorization_reference',
        ));

        foreach (['reference' => $reference, 'legal name' => $legalName, 'authorization reference' => $authorizationReference] as $label => $value) {
            if ($value === '') {
                throw new TreasuryConfigurationException("The public issuance escrow {$label} is required.");
            }
        }

        $principal = CommercialPrincipal::query()->where('reference', $reference)->first();

        if (! $commit) {
            return new CommercialPrincipalProvisioningData(
                status: $principal instanceof CommercialPrincipal ? 'existing' : 'would_create',
                committed: false,
                created: false,
                accountReady: $principal instanceof CommercialPrincipal && $principal->wallets()->where('slug', 'platform')->exists(),
                reference: $reference,
                legalName: $legalName,
                key: $principal instanceof CommercialPrincipal ? (string) $principal->getKey() : null,
                authorizationReference: $authorizationReference,
            );
        }

        return DB::transaction(function () use ($authorizationReference, $legalName, $reference): CommercialPrincipalProvisioningData {
            $created = false;
            $principal = CommercialPrincipal::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();
            $principal ??= CommercialPrincipal::query()->create([
                'reference' => $reference,
                'legal_name' => $legalName,
                'authorization_reference' => $authorizationReference,
                'active' => true,
                'metadata' => [
                    'interactive_login' => false,
                    'purpose' => 'public_issuance_escrow',
                    'provisioned_at' => now()->toIso8601String(),
                ],
            ]);
            $created = $principal->wasRecentlyCreated;

            if (! $principal->active || $principal->legal_name !== $legalName) {
                throw new TreasuryConfigurationException('The public issuance escrow principal does not match its configured identity.');
            }

            $this->accounts->open($principal, [
                'wallet' => [
                    'slug' => 'platform',
                    'name' => 'Public Issuance Escrow Account',
                ],
            ]);

            return new CommercialPrincipalProvisioningData(
                status: $created ? 'provisioned' : 'existing_ready',
                committed: true,
                created: $created,
                accountReady: true,
                reference: $reference,
                legalName: $legalName,
                key: (string) $principal->getKey(),
                authorizationReference: $authorizationReference,
            );
        }, attempts: 3);
    }
}
