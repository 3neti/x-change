<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Treasury\SystemPrincipalProvisioningData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class SystemPrincipalProvisioningService
{
    public function __construct(
        private WalletProvisioningContract $accounts,
        private SystemPrincipalAuthorizationReferenceResolver $authorizationReferences,
    ) {}

    public function inspect(?string $name = null, ?string $email = null): SystemPrincipalProvisioningData
    {
        return $this->resolve(
            name: $name,
            email: $email,
            authorizationReference: '',
            commit: false,
        );
    }

    public function provision(
        ?string $authorizationReference = null,
        ?string $name = null,
        ?string $email = null,
    ): SystemPrincipalProvisioningData {
        return $this->resolve(
            name: $name,
            email: $email,
            authorizationReference: $authorizationReference,
            commit: true,
        );
    }

    public function assertConfiguration(
        ?string $name = null,
        ?string $email = null,
    ): void {
        $this->configuration($name, $email);
    }

    private function resolve(
        ?string $name,
        ?string $email,
        ?string $authorizationReference,
        bool $commit,
    ): SystemPrincipalProvisioningData {
        [
            $modelClass,
            $identifierColumn,
            $configuredIdentifier,
            $email,
            $name,
        ] = $this->configuration($name, $email);

        if (! $commit) {
            $existing = $modelClass::query()
                ->where($identifierColumn, $configuredIdentifier)
                ->limit(2)
                ->get();

            if ($existing->count() > 1) {
                throw new TreasuryConfigurationException(
                    'The configured system principal matched more than one user.',
                );
            }

            $principal = $existing->first();

            return new SystemPrincipalProvisioningData(
                status: $principal instanceof Model ? 'existing' : 'would_create',
                committed: false,
                created: false,
                accountReady: $principal instanceof Model
                    && $this->hasSystemAccount($principal),
                model: $modelClass,
                identifierColumn: $identifierColumn,
                identifier: $configuredIdentifier,
                key: $principal instanceof Model
                    ? (string) $principal->getKey()
                    : null,
                authorizationReference: '',
            );
        }

        return DB::transaction(function () use (
            $authorizationReference,
            $configuredIdentifier,
            $email,
            $identifierColumn,
            $modelClass,
            $name,
        ): SystemPrincipalProvisioningData {
            $matches = $modelClass::query()
                ->where($identifierColumn, $configuredIdentifier)
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($matches->count() > 1) {
                throw new TreasuryConfigurationException(
                    'The configured system principal matched more than one user.',
                );
            }

            $principal = $matches->first();
            $created = false;
            $authorizationReference = $this->authorizationReferences->resolve(
                explicitReference: $authorizationReference,
                persistedReference: $principal instanceof Model
                    ? data_get($principal->getAttribute('onboarding_meta'), 'system_principal.authorization_reference')
                    : null,
                model: $modelClass,
                identifierColumn: $identifierColumn,
                identifier: $configuredIdentifier,
            );

            if (! $principal instanceof Model) {
                $principal = new $modelClass;

                if (! $principal instanceof Authenticatable) {
                    throw new TreasuryConfigurationException(
                        'The configured system-principal model must be authenticatable.',
                    );
                }

                $principal->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'onboarding_meta' => [
                        'system_principal' => [
                            'authorization_reference' => $authorizationReference,
                            'provisioned_at' => now()->toIso8601String(),
                            'interactive_login' => false,
                        ],
                    ],
                ]);
                $principal->save();
                $created = true;
            } else {
                $this->assertExistingPrincipal($principal, $email);
                $this->markSystemPrincipal(
                    $principal,
                    $authorizationReference,
                );
            }

            $this->accounts->open($principal, [
                'wallet' => [
                    'slug' => (string) config(
                        'x-change.payout.system_wallet_slug',
                        'platform',
                    ),
                    'name' => 'System Account',
                ],
            ]);

            return new SystemPrincipalProvisioningData(
                status: $created ? 'provisioned' : 'existing_ready',
                committed: true,
                created: $created,
                accountReady: true,
                model: $modelClass,
                identifierColumn: $identifierColumn,
                identifier: $configuredIdentifier,
                key: (string) $principal->getKey(),
                authorizationReference: $authorizationReference,
            );
        }, attempts: 3);
    }

    private function hasSystemAccount(Model $principal): bool
    {
        return method_exists($principal, 'wallet')
            && $principal->wallet()
                ->where('slug', (string) config(
                    'x-change.payout.system_wallet_slug',
                    'platform',
                ))
                ->exists();
    }

    /**
     * @return array{class-string<Model>, string, string, string, string}
     */
    private function configuration(?string $name, ?string $email): array
    {
        $modelClass = $this->modelClass();
        $identifierColumn = trim((string) config(
            'x-change.payout.system_user_column',
            'id',
        ));
        $configuredIdentifier = trim((string) config(
            'x-change.payout.system_user_id',
        ));

        if ($configuredIdentifier === '') {
            throw new TreasuryConfigurationException(
                'Treasury system principal [XCHANGE_SYSTEM_USER_ID] is required.',
            );
        }

        if ($identifierColumn !== 'email') {
            throw new TreasuryConfigurationException(
                'Automatic system-principal creation requires '
                .'[XCHANGE_SYSTEM_USER_COLUMN=email]. Existing principals may '
                .'continue using another stable identifier.',
            );
        }

        $email = mb_strtolower(trim($email ?? $configuredIdentifier));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new TreasuryConfigurationException(
                'The configured system-principal email is invalid.',
            );
        }

        if (! hash_equals(mb_strtolower($configuredIdentifier), $email)) {
            throw new TreasuryConfigurationException(
                'The system-principal email must match [XCHANGE_SYSTEM_USER_ID].',
            );
        }

        $name = trim($name ?? 'x-change System Principal');

        if ($name === '') {
            throw new TreasuryConfigurationException(
                'The system-principal name is required.',
            );
        }

        return [
            $modelClass,
            $identifierColumn,
            $configuredIdentifier,
            $email,
            $name,
        ];
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(): string
    {
        $modelClass = config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass)
            || ! class_exists($modelClass)
            || ! is_subclass_of($modelClass, Model::class)) {
            throw new TreasuryConfigurationException(
                'The configured system-principal model is invalid.',
            );
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass;
    }

    private function assertExistingPrincipal(Model $principal, string $email): void
    {
        if (mb_strtolower(trim((string) $principal->getAttribute('email'))) !== $email) {
            throw new TreasuryConfigurationException(
                'The resolved system principal conflicts with the configured email.',
            );
        }
    }

    private function markSystemPrincipal(
        Model $principal,
        string $authorizationReference,
    ): void {
        $onboardingMeta = (array) $principal->getAttribute('onboarding_meta');
        $existingReference = trim((string) data_get(
            $onboardingMeta,
            'system_principal.authorization_reference',
        ));

        if ($existingReference !== ''
            && ! hash_equals($existingReference, $authorizationReference)) {
            throw new TreasuryConfigurationException(
                'The existing system principal has a different authorization reference.',
            );
        }

        data_set(
            $onboardingMeta,
            'system_principal',
            [
                'authorization_reference' => $authorizationReference,
                'provisioned_at' => data_get(
                    $onboardingMeta,
                    'system_principal.provisioned_at',
                    now()->toIso8601String(),
                ),
                'interactive_login' => false,
            ],
        );

        $principal->setAttribute('onboarding_meta', $onboardingMeta);
        $principal->save();
    }
}
