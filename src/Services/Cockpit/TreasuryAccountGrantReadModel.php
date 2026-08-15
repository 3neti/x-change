<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleUserModelResolver;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class TreasuryAccountGrantReadModel
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private LifecycleUserModelResolver $users,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    /** @return array<string, mixed> */
    public function build(Model $operator): array
    {
        if (! $this->authority->allows($operator, TreasuryOperatorCapability::ViewAccountGrants)) {
            return [
                'schema' => 'x-change.cockpit.treasury-account-grants.v1',
                'can_view' => false,
                'can_request' => false,
                'can_approve' => false,
                'can_execute' => false,
                'test_allocations_available' => false,
                'connections' => [],
                'recipients' => [],
                'grants' => [],
            ];
        }

        $userModel = $this->users->resolve();

        return [
            'schema' => 'x-change.cockpit.treasury-account-grants.v1',
            'can_view' => true,
            'can_request' => $this->authority->allows($operator, TreasuryOperatorCapability::RequestAccountGrants),
            'can_approve' => $this->authority->allows($operator, TreasuryOperatorCapability::ApproveAccountGrants),
            'can_execute' => $this->authority->allows($operator, TreasuryOperatorCapability::ExecuteAccountGrants),
            'test_allocations_available' => ! app()->isProduction()
                && (bool) config('x-change.treasury_account_grants.test_allocations_enabled', false),
            'connections' => array_map(static fn ($connection): array => [
                'reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
            ], $this->connections->active()),
            'recipients' => $userModel::query()->latest()->limit(100)->get()->map(fn (Model $user): array => [
                'id' => (string) $user->getKey(),
                'name' => (string) ($user->getAttribute('name') ?: 'Account holder'),
                'identity' => $this->maskedIdentity($user),
            ])->values()->all(),
            'grants' => TreasuryAccountGrant::query()->with(['recipient', 'maker', 'checker'])
                ->latest()->limit(100)->get()->map(fn (TreasuryAccountGrant $grant): array => [
                    'reference' => $grant->reference,
                    'status' => $grant->status->value,
                    'recipient' => [
                        'name' => (string) ($grant->recipient?->getAttribute('name') ?: 'Account holder'),
                        'identity' => $grant->recipient instanceof Model ? $this->maskedIdentity($grant->recipient) : null,
                    ],
                    'amount' => '₱'.number_format($grant->amount_minor / 100, 2),
                    'purpose' => $grant->purpose,
                    'test_allocation' => $grant->test_allocation,
                    'maker' => (string) ($grant->maker?->getAttribute('name') ?: 'Named operator'),
                    'checker' => $grant->checker instanceof Model
                        ? (string) ($grant->checker->getAttribute('name') ?: 'Named operator')
                        : null,
                    'updated_at' => $grant->updated_at?->toIso8601String(),
                    'actions' => [
                        'approve' => route('x-change.cockpit.treasury.account-grants.approvals.store', $grant),
                        'execute' => route('x-change.cockpit.treasury.account-grants.executions.store', $grant),
                    ],
                ])->all(),
        ];
    }

    private function maskedIdentity(Model $user): string
    {
        $mobile = preg_replace('/\D+/', '', (string) $user->getAttribute('mobile'));

        if (is_string($mobile) && mb_strlen($mobile) >= 4) {
            return '•••• '.mb_substr($mobile, -4);
        }

        $email = (string) $user->getAttribute('email');

        return $email === '' ? 'Account available' : preg_replace('/(^.).*(@.*$)/', '$1•••$2', $email) ?? 'Account available';
    }
}
