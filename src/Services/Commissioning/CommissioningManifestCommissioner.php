<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commissioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class CommissioningManifestCommissioner
{
    public function __construct(
        private CommissioningManifestRepository $manifests,
        private SystemPrincipalProvisioningService $principals,
        private GeneratesVouchers $vouchers,
        private OnboardingVoucherInstructionPolicy $onboardingPolicy,
        private IssueSystemAccountFundingPayCode $fundedInvitations,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    /** @return array{schema: string, count: int, invitations: list<array{role: string, code: string|null, claim_url: string|null, created: bool}>, funding: array<string, mixed>|null} */
    public function commission(string $manifestReference): array
    {
        $manifest = $this->manifests->load($manifestReference);
        $this->assertSchema($manifest);

        $principal = $this->principals->provision(
            authorizationReference: $this->nullableString(data_get($manifest, 'system_principal.authorization_reference')),
            name: $this->nullableString(data_get($manifest, 'system_principal.name')),
            email: $this->nullableString(data_get($manifest, 'system_principal.email')),
        );
        $issuer = $this->issuer($principal->model, $principal->key);

        Auth::setUser($issuer);

        $namespace = $this->nonEmptyString(
            data_get($manifest, 'invitations.metadata_namespace'),
            'invitations.metadata_namespace',
        );
        $funding = $this->onboardingFunding($manifest);
        $roles = $this->roles($manifest);
        $fundingBefore = $this->assertFundedInvitationsCovered(
            $issuer,
            $roles,
            $funding,
        );
        $issued = collect($roles)
            ->map(fn (array $role): array => $this->ensureInvitation(
                $role,
                $issuer,
                $namespace,
                $funding,
                $manifestReference,
            ))
            ->values()
            ->all();

        return [
            'schema' => $this->nonEmptyString(
                data_get($manifest, 'invitations.schema', 'x-change.commissioning-invitations.v1'),
                'invitations.schema',
            ),
            'count' => count($issued),
            'invitations' => $issued,
            'funding' => $this->fundingFeedback(
                $funding,
                $fundingBefore,
                $this->fundedInvitationBalances($issuer, $funding),
            ),
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function assertSchema(array $manifest): void
    {
        $schema = $this->nonEmptyString(data_get($manifest, 'schema'), 'schema');

        if ($schema !== 'x-change.commissioning.manifest.v1') {
            throw new InvalidArgumentException("Unsupported commissioning manifest schema [{$schema}].");
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array{role: string, label: string, profile: string, prefix: string}>
     */
    private function roles(array $manifest): array
    {
        $roles = data_get($manifest, 'invitations.roles');

        if (! is_array($roles) || $roles === []) {
            throw new InvalidArgumentException('Commissioning manifest must declare at least one invitation role.');
        }

        return collect($roles)
            ->map(fn (mixed $role): array => $this->role($role))
            ->values()
            ->all();
    }

    /** @return array{role: string, label: string, profile: string, prefix: string} */
    private function role(mixed $role): array
    {
        if (! is_array($role)) {
            throw new InvalidArgumentException('Each commissioning invitation role must be a mapping.');
        }

        return [
            'role' => $this->nonEmptyString(data_get($role, 'role'), 'invitations.roles.role'),
            'label' => $this->nonEmptyString(data_get($role, 'label'), 'invitations.roles.label'),
            'profile' => $this->nonEmptyString(data_get($role, 'profile'), 'invitations.roles.profile'),
            'prefix' => strtoupper($this->nonEmptyString(data_get($role, 'prefix'), 'invitations.roles.prefix')),
        ];
    }

    /**
     * @param  array{role: string, label: string, profile: string, prefix: string}  $role
     * @param  array{amount_minor: int, currency: string, funding_source: string|null, authorization_reference: string|null, funding_instruction: string|null, connection_reference: string}  $funding
     */
    private function ensureInvitation(
        array $role,
        Model $issuer,
        string $namespace,
        array $funding,
        string $manifestReference,
    ): array
    {
        $existing = Voucher::query()
            ->get()
            ->first(fn (Voucher $voucher): bool => data_get(
                $voucher->metadata,
                'instructions.metadata.custom.'.$namespace.'.role',
            ) === $role['role']);

        if ($existing instanceof Voucher) {
            return $this->invitationPayload($role['role'], (string) $existing->code, false);
        }

        if ($funding['amount_minor'] > 0) {
            return $this->ensureFundedInvitation(
                $role,
                $issuer,
                $namespace,
                $funding,
                $manifestReference,
            );
        }

        $input = $this->onboardingPolicy->normalize([
            'cash' => [
                'amount' => 0,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => $role['label'].' onboarding invitation',
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'count' => 1,
            'prefix' => $role['prefix'],
            'mask' => '****',
            'voucher_type' => VoucherType::REDEEMABLE->value,
            'onboarding' => true,
            'claim' => [
                'outcomes' => [['key' => 'provider_disbursement']],
                'selection' => 'server',
                'consumption' => 'one_of',
                'default_outcome' => 'provider_disbursement',
                'onboarding' => [
                    'mode' => 'required',
                    'profile' => $role['profile'],
                ],
                'claimant' => ['mode' => 'unbound'],
                'profile' => 'voucher.claim.v1',
            ],
            'metadata' => [
                'flow_type' => 'disbursable',
                'issuer_id' => (string) $issuer->getKey(),
                'custom' => [
                    $namespace => [
                        'role' => $role['role'],
                        'label' => $role['label'],
                        'profile' => $role['profile'],
                        'funding_instruction' => $funding['funding_instruction'],
                    ],
                ],
            ],
        ]);

        $voucher = $this->vouchers->handle(VoucherInstructionsData::from($input))->first();

        if (! $voucher instanceof Voucher) {
            return $this->invitationPayload($role['role'], null, false);
        }

        return $this->invitationPayload($role['role'], (string) $voucher->code, true);
    }


    /**
     * @param  list<array{role: string, label: string, profile: string, prefix: string}>  $roles
     * @param  array{amount_minor: int, currency: string, funding_source: string|null, authorization_reference: string|null, funding_instruction: string|null, connection_reference: string}  $funding
     * @return array{account_funding_reserve_minor: int, pay_code_reserve_minor: int, currency: string, connection_reference: string}|null
     */
    private function assertFundedInvitationsCovered(Model $issuer, array $roles, array $funding): ?array
    {
        if ($funding['amount_minor'] <= 0) {
            return null;
        }

        $balances = $this->fundedInvitationBalances($issuer, $funding);
        $requiredMinor = $funding['amount_minor'] * count($roles);

        if ($balances['account_funding_reserve_minor'] < $requiredMinor) {
            throw new InvalidArgumentException(
                'The system Account Funding Reserve does not cover funded commissioning invitations.',
            );
        }

        return $balances;
    }

    /**
     * @param  array{amount_minor: int, currency: string, funding_source: string|null, authorization_reference: string|null, funding_instruction: string|null, connection_reference: string}  $funding
     * @return array{account_funding_reserve_minor: int, pay_code_reserve_minor: int, currency: string, connection_reference: string}|null
     */
    private function fundedInvitationBalances(Model $issuer, array $funding): ?array
    {
        if ($funding['amount_minor'] <= 0) {
            return null;
        }

        $connection = collect($this->connections->active([
            $funding['connection_reference'],
        ]))->sole();

        if ($connection->currency !== $funding['currency']) {
            throw new InvalidArgumentException(
                'Funded commissioning invitation currency must match the Treasury connection currency.',
            );
        }

        $this->portfolios->provision($issuer, [
            $connection->reference,
        ]);
        $principal = $this->principalReferences->resolve($issuer);
        $positions = collect($this->positions->forPrincipal($principal));

        return [
            'account_funding_reserve_minor' => (int) ($positions->first(
                static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::AccountFundingReserve,
            )?->balanceMinor ?? 0),
            'pay_code_reserve_minor' => (int) ($positions->first(
                static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::PayCodeReserve,
            )?->balanceMinor ?? 0),
            'currency' => $connection->currency,
            'connection_reference' => $connection->reference,
        ];
    }

    /**
     * @param  array{amount_minor: int, currency: string, funding_source: string|null, authorization_reference: string|null, funding_instruction: string|null, connection_reference: string}  $funding
     * @param  array{account_funding_reserve_minor: int, pay_code_reserve_minor: int, currency: string, connection_reference: string}|null  $before
     * @param  array{account_funding_reserve_minor: int, pay_code_reserve_minor: int, currency: string, connection_reference: string}|null  $after
     * @return array<string, mixed>|null
     */
    private function fundingFeedback(array $funding, ?array $before, ?array $after): ?array
    {
        if ($funding['amount_minor'] <= 0 || $before === null || $after === null) {
            return null;
        }

        return [
            'schema' => 'x-change.commissioning-funding.v1',
            'source' => $funding['funding_source'],
            'connection_reference' => $after['connection_reference'],
            'currency' => $after['currency'],
            'invitation_amount_minor' => $funding['amount_minor'],
            'opening_reserve_minor' => $before['account_funding_reserve_minor'],
            'account_funding_reserve_after_minor' => $after['account_funding_reserve_minor'],
            'pay_code_reserve_after_minor' => $after['pay_code_reserve_minor'],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{amount_minor: int, currency: string, funding_source: string|null, authorization_reference: string|null, funding_instruction: string|null, connection_reference: string}
     */
    private function onboardingFunding(array $manifest): array
    {
        $amount = (float) data_get(
            $manifest,
            'onboarding.invitation_amount',
            0,
        );
        $amountMinor = (int) round($amount * 100);
        $currency = strtoupper(trim(
            (string) data_get($manifest, 'onboarding.currency', 'PHP'),
        ));
        $fundingSource = $this->nullableString(data_get(
            $manifest,
            'onboarding.funding_source',
        ));
        $authorizationReference = $this->nullableString(data_get(
            $manifest,
            'onboarding.authorization_reference',
        ));
        $connectionReference = trim((string) data_get(
            $manifest,
            'onboarding.connection_reference',
            'netbank-primary',
        ));

        if ($amountMinor <= 0) {
            return [
                'amount_minor' => 0,
                'currency' => $currency === '' ? 'PHP' : $currency,
                'funding_source' => $fundingSource,
                'authorization_reference' => $authorizationReference,
                'funding_instruction' => $this->nullableString(data_get(
                    $manifest,
                    'onboarding.funding_instruction',
                )),
                'connection_reference' => $connectionReference === '' ? 'netbank-primary' : $connectionReference,
            ];
        }

        if ($currency === '') {
            throw new InvalidArgumentException(
                'Commissioning manifest field [onboarding.currency] is required for funded invitations.',
            );
        }

        if ($fundingSource !== 'treasury_account_funding_reserve') {
            throw new InvalidArgumentException(
                'Funded commissioning invitations require [onboarding.funding_source] to be [treasury_account_funding_reserve].',
            );
        }

        if ($authorizationReference === null) {
            throw new InvalidArgumentException(
                'Funded commissioning invitations require [onboarding.authorization_reference].',
            );
        }

        if ($connectionReference === '') {
            throw new InvalidArgumentException(
                'Funded commissioning invitations require [onboarding.connection_reference].',
            );
        }

        return [
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'funding_source' => $fundingSource,
            'authorization_reference' => $authorizationReference,
            'funding_instruction' => $this->nullableString(data_get(
                $manifest,
                'onboarding.funding_instruction',
                'Client funds ready',
            )),
            'connection_reference' => $connectionReference,
        ];
    }

    /**
     * @param  array{role: string, label: string, profile: string, prefix: string}  $role
     * @param  array{amount_minor: int, currency: string, funding_source: string|null, authorization_reference: string|null, funding_instruction: string|null, connection_reference: string}  $funding
     * @return array{role: string, code: string|null, claim_url: string|null, created: bool}
     */
    private function ensureFundedInvitation(
        array $role,
        Model $issuer,
        string $namespace,
        array $funding,
        string $manifestReference,
    ): array
    {
        $idempotencyReference = implode(':', [
            'commissioning-invitation',
            sha1($manifestReference),
            $role['role'],
            (string) $funding['amount_minor'],
            $funding['currency'],
            $funding['funding_source'],
            sha1((string) $funding['authorization_reference']),
        ]);

        $issuance = $this->fundedInvitations->handle(new IssueSystemAccountFundingPayCodeData(
            amountMinor: $funding['amount_minor'],
            connectionReference: $funding['connection_reference'],
            idempotencyReference: $idempotencyReference,
            expiresAt: now()->addYear(),
            recipient: null,
            evidenceReference: 'commissioning-manifest:'
                .$role['role']
                .':'
                .$funding['amount_minor'],
            authorizationReference: $funding['authorization_reference'],
            source: 'commissioning_invitation',
            metadata: [
                'flow_type' => 'disbursable',
                'issuer_id' => (string) $issuer->getKey(),
                'custom' => [
                    $namespace => [
                        'role' => $role['role'],
                        'label' => $role['label'],
                        'profile' => $role['profile'],
                        'funding_source' => $funding['funding_source'],
                        'funding_instruction' => $funding['funding_instruction'],
                        'authorization_reference' => $funding['authorization_reference'],
                    ],
                ],
                'onboarding_grant' => [
                    'enabled' => true,
                    'funding_instruction' => $funding['funding_instruction'],
                    'funding_source' => $funding['funding_source'],
                ],
            ],
            onboarding: true,
            prefix: $role['prefix'],
            mask: '****',
            riderMessage: $role['label'].' onboarding invitation',
            onboardingProfile: $role['profile'],
        ));

        $voucher = $issuance->voucher;

        if (! $voucher instanceof Voucher) {
            return $this->invitationPayload($role['role'], null, false);
        }

        return $this->invitationPayload(
            $role['role'],
            (string) $voucher->code,
            $issuance->wasRecentlyCreated,
        );
    }

    /** @return array{role: string, code: string|null, claim_url: string|null, created: bool} */
    private function invitationPayload(string $role, ?string $code, bool $created): array
    {
        return [
            'role' => $role,
            'code' => $code,
            'claim_url' => $code === null ? null : route('x-change.claim.show', ['code' => $code]),
            'created' => $created,
        ];
    }

    /** @param class-string<Model> $model */
    private function issuer(string $model, ?string $key): Model
    {
        if ($key === null || $key === '') {
            throw new InvalidArgumentException('The system principal could not be resolved.');
        }

        $issuer = $model::query()->whereKey($key)->first();

        if (! $issuer instanceof Model) {
            throw new InvalidArgumentException('The system principal model could not be loaded.');
        }

        return $issuer;
    }

    private function nonEmptyString(mixed $value, string $field): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException("Commissioning manifest field [{$field}] is required.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
