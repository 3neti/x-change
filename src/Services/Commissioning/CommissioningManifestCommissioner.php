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
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;

final readonly class CommissioningManifestCommissioner
{
    public function __construct(
        private CommissioningManifestRepository $manifests,
        private SystemPrincipalProvisioningService $principals,
        private GeneratesVouchers $vouchers,
        private OnboardingVoucherInstructionPolicy $onboardingPolicy,
    ) {}

    /** @return array{schema: string, count: int, invitations: list<array{role: string, code: string|null, claim_url: string|null, created: bool}>} */
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
        $roles = $this->roles($manifest);
        $issued = collect($roles)
            ->map(fn (array $role): array => $this->ensureInvitation($role, $issuer, $namespace))
            ->values()
            ->all();

        return [
            'schema' => $this->nonEmptyString(
                data_get($manifest, 'invitations.schema', 'x-change.commissioning-invitations.v1'),
                'invitations.schema',
            ),
            'count' => count($issued),
            'invitations' => $issued,
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

    /** @param array{role: string, label: string, profile: string, prefix: string} $role */
    private function ensureInvitation(array $role, Model $issuer, string $namespace): array
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
