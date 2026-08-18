<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake\Contributors;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;

final readonly class AccountSnapshotKeepsakeContributor implements InstanceKeepsakeContributor
{
    public function __construct(
        private CockpitHeaderReadModelProviderContract $header,
        private CanonicalKeepsakeJson $json,
    ) {}

    public function key(): string
    {
        return 'accounts';
    }

    public function snapshotSchemaVersion(): int
    {
        return 1;
    }

    public function blueprintSchemaVersion(): ?int
    {
        return 1;
    }

    public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution
    {
        if (! $context->includes('accounts')) {
            return new InstanceKeepsakeContribution($this->key(), 1, 1);
        }

        $accounts = [];
        $invitations = [];

        foreach ($context->users as $user) {
            $model = $user['model'];
            $metrics = [];

            foreach ($this->header->forOperator($model)->balances as $metric) {
                if (! $metric instanceof CockpitDashboardMetricData) {
                    continue;
                }

                $metrics[$metric->key] = $metric->amount_minor;
            }

            $accounts[] = [
                'account_reference' => $user['reference'],
                'profile' => $this->profile($model, $context->includePersonalData),
                'currency' => $context->currency,
                'client_funds_minor' => $metrics['internal'] ?? null,
                'outstanding_pay_codes_minor' => $metrics['outstanding'] ?? null,
                'issuance_capacity_minor' => $metrics['issuance'] ?? null,
                'observed_at' => $context->observedAt,
                'authority' => 'observational_snapshot',
                'restorable' => false,
                'reconciliation_required' => true,
            ];

            if ($context->includes('blueprint')
                && $context->includePersonalData
                && ! $this->isSystemPrincipal($model)) {
                $invitations[] = [
                    'reference' => $user['reference'],
                    'profile' => $this->profile($model, true),
                    'desired_state' => 'pending',
                    'enabled' => false,
                    'requires_reverification' => true,
                    'credentials_included' => false,
                    'authority_included' => false,
                    'financial_state_included' => false,
                ];
            }
        }

        return new InstanceKeepsakeContribution(
            key: $this->key(),
            snapshotSchemaVersion: 1,
            blueprintSchemaVersion: 1,
            snapshotFiles: [
                'snapshot/accounts.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.accounts.v1',
                    'accounts' => $accounts,
                ]),
            ],
            blueprintFiles: $context->includes('blueprint') ? [
                'blueprint/account-invitations.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.account-invitations.v1',
                    'inert' => true,
                    'importer_included' => false,
                    'invitations' => $invitations,
                ]),
            ] : [],
            summary: [
                'accounts' => count($accounts),
                'bootstrap_invitations' => count($invitations),
            ],
        );
    }

    /** @return array{name:?string,email:?string,mobile:?string}|array{redacted:bool} */
    private function profile(Model $model, bool $includePersonalData): array
    {
        if (! $includePersonalData) {
            return ['redacted' => true];
        }

        return [
            'name' => $this->attribute($model, 'name'),
            'email' => $this->attribute($model, 'email'),
            'mobile' => $this->attribute($model, 'mobile'),
        ];
    }

    private function attribute(Model $model, string $key): ?string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : null;
    }

    private function isSystemPrincipal(Model $model): bool
    {
        $column = trim((string) config('x-change.payout.system_user_column'));
        $identifier = trim((string) config('x-change.payout.system_user_id'));

        return $column !== ''
            && $identifier !== ''
            && hash_equals($identifier, (string) $model->getAttribute($column));
    }
}
