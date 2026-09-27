<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Commercial\CommercialPrincipalProvisioningData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class CommercialPrincipalProvisioningService
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

    public function assertConfiguration(): void
    {
        $this->configuration();
    }

    private function resolve(bool $commit): CommercialPrincipalProvisioningData
    {
        [$reference, $legalName, $authorizationReference, $accountSlug] = $this->configuration();

        if (! $commit) {
            $principals = CommercialPrincipal::query()
                ->where('reference', $reference)
                ->limit(2)
                ->get();

            if ($principals->count() > 1) {
                throw new TreasuryConfigurationException(
                    'The configured commercial principal matched more than one record.',
                );
            }

            $principal = $principals->first();

            return new CommercialPrincipalProvisioningData(
                status: $principal instanceof CommercialPrincipal ? 'existing' : 'would_create',
                committed: false,
                created: false,
                accountReady: $principal instanceof CommercialPrincipal
                    && $this->hasRevenueAccount($principal, $accountSlug),
                reference: $reference,
                legalName: $legalName,
                key: $principal instanceof CommercialPrincipal
                    ? (string) $principal->getKey()
                    : null,
                authorizationReference: $authorizationReference,
            );
        }

        return DB::transaction(function () use (
            $accountSlug,
            $authorizationReference,
            $legalName,
            $reference,
        ): CommercialPrincipalProvisioningData {
            $principals = CommercialPrincipal::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($principals->count() > 1) {
                throw new TreasuryConfigurationException(
                    'The configured commercial principal matched more than one record.',
                );
            }

            $principal = $principals->first();
            $created = false;

            if (! $principal instanceof CommercialPrincipal) {
                $principal = CommercialPrincipal::query()->create([
                    'reference' => $reference,
                    'legal_name' => $legalName,
                    'authorization_reference' => $authorizationReference,
                    'active' => true,
                    'metadata' => [
                        'interactive_login' => false,
                        'provisioned_at' => now()->toIso8601String(),
                    ],
                ]);
                $created = true;
            } else {
                $this->assertExistingPrincipal(
                    $principal,
                    $legalName,
                    $authorizationReference,
                );
            }

            $this->accounts->open($principal, [
                'wallet' => [
                    'slug' => $accountSlug,
                    'name' => 'Commercial Revenue Account',
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

    /**
     * @return array{string, string, string, string}
     */
    private function configuration(): array
    {
        $reference = trim((string) config('x-change.commercial.principal.reference'));
        $legalName = trim((string) config('x-change.commercial.principal.legal_name'));
        $authorizationReference = trim((string) config(
            'x-change.commercial.principal.authorization_reference',
        ));
        $accountSlug = trim((string) config(
            'x-change.commercial.principal.revenue_account_slug',
        ));

        foreach ([
            'reference' => $reference,
            'legal name' => $legalName,
            'authorization reference' => $authorizationReference,
            'revenue Account slug' => $accountSlug,
        ] as $label => $value) {
            if ($value === '') {
                throw new TreasuryConfigurationException(
                    "The commercial principal {$label} is required.",
                );
            }
        }

        return [$reference, $legalName, $authorizationReference, $accountSlug];
    }

    private function hasRevenueAccount(
        CommercialPrincipal $principal,
        string $accountSlug,
    ): bool {
        return $principal->wallets()->where('slug', $accountSlug)->exists();
    }

    private function assertExistingPrincipal(
        CommercialPrincipal $principal,
        string $legalName,
        string $authorizationReference,
    ): void {
        if (! $principal->active) {
            throw new TreasuryConfigurationException(
                'The configured commercial principal is inactive.',
            );
        }

        if (! hash_equals($principal->legal_name, $legalName)) {
            throw new TreasuryConfigurationException(
                'The existing commercial principal has a different legal name.',
            );
        }

        if (! hash_equals($principal->authorization_reference, $authorizationReference)) {
            throw new TreasuryConfigurationException(
                'The existing commercial principal has a different authorization reference.',
            );
        }
    }
}
