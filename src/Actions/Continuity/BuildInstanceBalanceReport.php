<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Continuity;

use BackedEnum;
use Carbon\CarbonImmutable;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Keepsake\KeepsakeUserModelResolver;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class BuildInstanceBalanceReport
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private KeepsakeUserModelResolver $users,
        private CanonicalKeepsakeJson $json,
    ) {}

    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $asOf): array
    {
        $asOf = $asOf->utc();
        $blockers = [];
        $warnings = [];
        $instanceId = trim((string) config('x-change.instance.id'));

        if ($instanceId === '') {
            $blockers[] = 'source_instance_id_missing';
        }

        $providerSnapshots = $this->providerSnapshots($asOf);
        $connectionReports = [];
        $positionsByPrincipal = [];
        $connectionReserveTotals = [];

        $connections = $this->connections->all();

        if ($connections === []) {
            $blockers[] = 'treasury_connections_missing';
        }

        foreach ($connections as $connection) {
            $connectionReport = $this->connectionReport(
                $connection,
                $providerSnapshots,
                $positionsByPrincipal,
                $blockers,
                $warnings,
            );
            $connectionReports[] = $connectionReport;
            $connectionReserveTotals[$connection->reference] = (int) data_get(
                $connectionReport,
                'positions.by_purpose.'.TreasuryPositionPurpose::PayCodeReserve->value,
                0,
            );
        }

        $accountFacts = $this->payCodeFacts($asOf, $blockers);
        $accounts = $this->accounts(
            $positionsByPrincipal,
            $connectionReports,
            $connectionReserveTotals,
            $accountFacts,
            $warnings,
            $blockers,
        );

        $blockers = $this->sortedUnique($blockers);
        $warnings = $this->sortedUnique($warnings);
        $status = $blockers === [] ? 'complete' : 'incomplete';

        $report = [
            'schema' => 'x-change.instance-balance-report.v1',
            'status' => $status,
            'as_of' => $asOf->toIso8601String(),
            'source_instance' => [
                'id' => $instanceId !== '' ? $instanceId : null,
                'name' => (string) config('app.name'),
                'deployment_profile' => (string) config('x-change.deployment.profile'),
                'runtime_tier' => (string) config('x-change.deployment.runtime_tier'),
                'x_change_version' => InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev',
            ],
            'safety' => [
                'read_only' => true,
                'writes_database' => false,
                'writes_cache' => false,
                'writes_journal' => false,
                'provider_calls' => false,
                'moves_money' => false,
                'transfers_balances' => false,
                'restores_accounts' => false,
                'restores_pay_codes' => false,
                'authorizes_financial_action' => false,
                'evidence_only' => true,
            ],
            'provider_balance_snapshots' => $providerSnapshots,
            'treasury_connections' => $connectionReports,
            'accounts' => $accounts,
            'controls' => [
                'currency_totals_are_separate' => true,
                'provider_connections_are_separate' => true,
                'all_inventory_equals_positions' => collect($connectionReports)
                    ->where('active', true)
                    ->every(static fn (array $connection): bool => data_get(
                        $connection,
                        'control.inventory_equals_positions',
                    ) === true),
            ],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'disclaimer' => 'Evidence only. This report does not transfer, recreate, credit, settle, or authorize any balance, Account, or Pay Code.',
        ];

        return [
            'report' => $report,
            'report_sha256' => $this->json->hash($report),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $providerSnapshots
     * @param  array<string, list<TreasuryPositionData>>  $positionsByPrincipal
     * @param  list<string>  $blockers
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function connectionReport(
        TreasuryProviderConnectionData $connection,
        array $providerSnapshots,
        array &$positionsByPrincipal,
        array &$blockers,
        array &$warnings,
    ): array {
        $positions = array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active',
        ));

        foreach ($positions as $position) {
            $positionsByPrincipal[$position->principalReference][] = $position;
        }

        $byPurpose = $this->positionsByPurpose($positions);
        $positionTotalMinor = array_sum(array_column($byPurpose, 'balance_minor'));
        $inventory = $this->inventories->find($connection->inventoryReference);
        $matchingSnapshots = array_values(array_filter(
            $providerSnapshots,
            static fn (array $snapshot): bool => $snapshot['provider'] === $connection->provider
                && $snapshot['currency'] === $connection->currency,
        ));
        $freshSnapshots = array_values(array_filter(
            $matchingSnapshots,
            static fn (array $snapshot): bool => $snapshot['is_stale'] === false,
        ));

        $inventoryEqualsPositions = $inventory === null
            ? null
            : $inventory->balanceMinor === $positionTotalMinor;

        if ($connection->isActive() && $inventory === null) {
            $blockers[] = 'inventory_missing:'.$connection->reference;
        }

        if ($inventoryEqualsPositions === false) {
            $blockers[] = 'inventory_position_mismatch:'.$connection->reference;
        }

        if ($connection->isActive() && $matchingSnapshots === []) {
            $blockers[] = 'provider_snapshot_missing:'.$connection->reference;
        } elseif ($connection->isActive() && $freshSnapshots === []) {
            $blockers[] = 'provider_snapshot_stale:'.$connection->reference;
        } elseif (count($freshSnapshots) > 1) {
            $warnings[] = 'provider_snapshot_ambiguous:'.$connection->reference;
        }

        return [
            'reference' => $connection->reference,
            'provider' => $connection->provider,
            'mode' => $connection->mode->value,
            'active' => $connection->isActive(),
            'currency' => $connection->currency,
            'decimal_places' => $connection->decimalPlaces,
            'inventory' => $inventory === null
                ? [
                    'reference' => $connection->inventoryReference,
                    'status' => 'not_registered',
                    'balance_minor' => null,
                ]
                : [
                    'reference' => $connection->inventoryReference,
                    'status' => $inventory->status,
                    'balance_minor' => $inventory->balanceMinor,
                ],
            'positions' => [
                'count' => count($positions),
                'balance_minor' => $positionTotalMinor,
                'by_purpose' => collect($byPurpose)
                    ->mapWithKeys(static fn (array $purpose): array => [
                        $purpose['purpose'] => $purpose['balance_minor'],
                    ])
                    ->all(),
            ],
            'provider_snapshot_candidates' => array_map(
                static fn (array $snapshot): string => $snapshot['snapshot_reference'],
                $matchingSnapshots,
            ),
            'provider_liquidity' => count($freshSnapshots) === 1
                ? [
                    'status' => 'fresh',
                    'balance_minor' => $freshSnapshots[0]['available_balance_minor']
                        ?? $freshSnapshots[0]['balance_minor'],
                    'snapshot_reference' => $freshSnapshots[0]['snapshot_reference'],
                ]
                : [
                    'status' => $matchingSnapshots === []
                        ? 'missing'
                        : ($freshSnapshots === [] ? 'stale' : 'ambiguous'),
                    'balance_minor' => null,
                    'snapshot_reference' => null,
                ],
            'control' => [
                'inventory_equals_positions' => $inventoryEqualsPositions,
                'difference_minor' => $inventory === null
                    ? null
                    : $inventory->balanceMinor - $positionTotalMinor,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function providerSnapshots(CarbonImmutable $asOf): array
    {
        $maximumAgeSeconds = (int) config('x-change.funding.provider_balance_max_age_seconds', 300);
        $snapshots = [];

        foreach (ProviderBalanceSnapshot::query()
            ->select([
                'provider_code', 'balance_key', 'scope_key', 'balance_minor',
                'available_balance_minor', 'currency', 'account_reference_masked',
                'provider_as_of', 'fetched_at', 'refresh_status',
            ])
            ->orderBy('provider_code')
            ->orderBy('currency')
            ->orderBy('balance_key')
            ->orderBy('scope_key')
            ->cursor() as $snapshot) {
            $fetchedAt = $snapshot->fetched_at === null
                ? null
                : CarbonImmutable::instance($snapshot->fetched_at)->utc();
            $isStale = $snapshot->refresh_status !== 'fresh'
                || $fetchedAt === null
                || $fetchedAt->lessThan($asOf->subSeconds($maximumAgeSeconds));
            $referenceSource = implode('|', [
                $snapshot->provider_code,
                $snapshot->currency,
                $snapshot->balance_key,
                $snapshot->scope_key,
            ]);

            $snapshots[] = [
                'snapshot_reference' => 'snapshot:'.hash('sha256', $referenceSource),
                'provider' => (string) $snapshot->provider_code,
                'balance_key' => (string) $snapshot->balance_key,
                'scope_key' => (string) $snapshot->scope_key,
                'currency' => (string) $snapshot->currency,
                'balance_minor' => $snapshot->balance_minor,
                'available_balance_minor' => $snapshot->available_balance_minor,
                'account_reference_masked' => $this->maskAccountReference(
                    $snapshot->account_reference_masked,
                ),
                'provider_as_of' => $snapshot->provider_as_of?->toIso8601String(),
                'fetched_at' => $fetchedAt?->toIso8601String(),
                'refresh_status' => (string) $snapshot->refresh_status,
                'is_stale' => $isStale,
                'maximum_age_seconds' => $maximumAgeSeconds,
                'authority' => 'persisted_observational_snapshot',
            ];
        }

        return $snapshots;
    }

    /**
     * @param  array<string, list<TreasuryPositionData>>  $positionsByPrincipal
     * @param  list<array<string, mixed>>  $connectionReports
     * @param  array<string, int>  $connectionReserveTotals
     * @param  array<string, array<string, mixed>>  $payCodeFacts
     * @param  list<string>  $warnings
     * @param  list<string>  $blockers
     * @return list<array<string, mixed>>
     */
    private function accounts(
        array $positionsByPrincipal,
        array $connectionReports,
        array $connectionReserveTotals,
        array $payCodeFacts,
        array &$warnings,
        array &$blockers,
    ): array {
        $model = $this->users->resolve();
        $instance = new $model;
        $count = $model::query()->count();
        $limit = (int) config('x-change.instance_keepsake.max_users', 1_000);

        if ($count > $limit) {
            $blockers[] = 'account_limit_exceeded';

            return [];
        }

        $accounts = [];

        foreach ($model::query()->orderBy($instance->getQualifiedKeyName())->cursor() as $account) {
            $principalReference = $this->principalReferences->resolve($account);
            $positions = $positionsByPrincipal[$principalReference] ?? [];
            $accountReference = $this->fingerprint('account', $principalReference);

            if ($positions === []) {
                $warnings[] = 'account_positions_missing:'.$accountReference;
            }

            $balances = [];

            foreach ($connectionReports as $connection) {
                if ($connection['active'] !== true) {
                    continue;
                }

                $connectionPositions = array_values(array_filter(
                    $positions,
                    static fn (TreasuryPositionData $position): bool => $position->provider === $connection['provider']
                        && $position->connectionReference === $connection['reference']
                        && $position->currency === $connection['currency']
                        && $position->status === 'active',
                ));
                $clientFundsMinor = $this->sumPurpose(
                    $connectionPositions,
                    TreasuryPositionPurpose::ClientFunds,
                );
                $payCodeReserveMinor = $this->sumPurpose(
                    $connectionPositions,
                    TreasuryPositionPurpose::PayCodeReserve,
                );
                $providerLiquidityMinor = data_get($connection, 'provider_liquidity.balance_minor');
                $connectionReserveMinor = $connectionReserveTotals[$connection['reference']] ?? 0;
                $issuanceCapacityMinor = is_int($providerLiquidityMinor)
                    ? max(0, min(
                        $clientFundsMinor,
                        $providerLiquidityMinor - $connectionReserveMinor,
                    ))
                    : null;

                $balances[] = [
                    'provider' => $connection['provider'],
                    'connection_reference' => $connection['reference'],
                    'currency' => $connection['currency'],
                    'client_funds_minor' => $clientFundsMinor,
                    'outstanding_pay_codes_minor' => $payCodeReserveMinor,
                    'issuance_capacity' => [
                        'amount_minor' => $issuanceCapacityMinor,
                        'inputs' => [
                            'client_funds_minor' => $clientFundsMinor,
                            'connection_pay_code_reserve_minor' => $connectionReserveMinor,
                            'provider_liquidity_minor' => $providerLiquidityMinor,
                            'provider_liquidity_status' => data_get(
                                $connection,
                                'provider_liquidity.status',
                            ),
                        ],
                    ],
                ];
            }

            $ownerKeys = array_values(array_unique([
                $account::class.'|'.$account->getKey(),
                $account->getMorphClass().'|'.$account->getKey(),
            ]));
            $facts = $this->emptyPayCodeFacts();

            foreach ($ownerKeys as $ownerKey) {
                $facts = $this->mergePayCodeFacts($facts, $payCodeFacts[$ownerKey] ?? []);
            }

            $accounts[] = [
                'account_reference' => $accountReference,
                'principal_fingerprint' => $this->fingerprint('principal', $principalReference),
                'identifier' => $this->maskedIdentifier($account),
                'balances' => $balances,
                'pay_code_record_facts' => $facts,
            ];
        }

        return $accounts;
    }

    /**
     * @param  list<string>  $blockers
     * @return array<string, array<string, mixed>>
     */
    private function payCodeFacts(CarbonImmutable $asOf, array &$blockers): array
    {
        $limit = (int) config('x-change.instance_keepsake.max_pay_codes', 10_000);

        if (Voucher::query()->count() > $limit) {
            $blockers[] = 'pay_code_limit_exceeded';

            return [];
        }

        $facts = [];

        foreach (Voucher::query()
            ->select(['id', 'owner_type', 'owner_id', 'state', 'redeemed_at', 'expires_at'])
            ->orderBy('id')
            ->cursor() as $voucher) {
            $ownerKey = (string) $voucher->owner_type.'|'.$voucher->owner_id;
            $facts[$ownerKey] ??= $this->emptyPayCodeFacts();
            $state = $this->enumValue($voucher->state) ?? 'unknown';
            $facts[$ownerKey]['count']++;
            $facts[$ownerKey]['persisted_state_counts'][$state] = (
                $facts[$ownerKey]['persisted_state_counts'][$state] ?? 0
            ) + 1;

            if ($voucher->redeemed_at !== null
                && CarbonImmutable::instance($voucher->redeemed_at)->utc()->lessThanOrEqualTo($asOf)) {
                $facts[$ownerKey]['redeemed_count']++;
            } elseif ($voucher->expires_at !== null
                && CarbonImmutable::instance($voucher->expires_at)->utc()->lessThanOrEqualTo($asOf)) {
                $facts[$ownerKey]['expired_unredeemed_count']++;
            } elseif (in_array($state, ['closed', 'cancelled'], true)) {
                $facts[$ownerKey]['cancelled_count']++;
            } else {
                $facts[$ownerKey]['open_record_count']++;
            }
        }

        foreach ($facts as &$accountFacts) {
            ksort($accountFacts['persisted_state_counts']);
        }

        return $facts;
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     * @return list<array{purpose:string,balance_minor:int,count:int}>
     */
    private function positionsByPurpose(array $positions): array
    {
        return collect($positions)
            ->groupBy(static fn (TreasuryPositionData $position): string => $position->purpose->value)
            ->map(static fn ($group, string $purpose): array => [
                'purpose' => $purpose,
                'balance_minor' => $group->sum(
                    static fn (TreasuryPositionData $position): int => $position->balanceMinor,
                ),
                'count' => $group->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /** @param list<TreasuryPositionData> $positions */
    private function sumPurpose(array $positions, TreasuryPositionPurpose $purpose): int
    {
        return array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->purpose === $purpose
                ? $position->balanceMinor
                : 0,
            $positions,
        ));
    }

    /** @return array<string, mixed> */
    private function maskedIdentifier(Model $account): array
    {
        return [
            'name' => $this->maskName($account->getAttribute('name')),
            'email' => $this->maskEmail($account->getAttribute('email')),
            'mobile' => $this->maskMobile($account->getAttribute('mobile')),
        ];
    }

    private function maskName(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : mb_substr($value, 0, 1).'***';
    }

    private function maskEmail(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : mb_substr($value, 0, 1).'***@***';
    }

    private function maskMobile(mixed $value): ?string
    {
        $value = is_scalar($value) ? preg_replace('/\D+/', '', (string) $value) : '';

        return $value === '' ? null : '*******'.mb_substr($value, -4);
    }

    private function maskAccountReference(mixed $value): ?string
    {
        $value = is_scalar($value) ? preg_replace('/\s+/', '', (string) $value) : '';

        return $value === '' ? null : '********'.mb_substr($value, -4);
    }

    private function fingerprint(string $kind, string $value): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('The application key is required to protect balance-report identifiers.');
        }

        return $kind.':'.hash_hmac('sha256', 'x-change-balance-report|'.$kind.'|'.$value, $key);
    }

    /** @return array<string, mixed> */
    private function emptyPayCodeFacts(): array
    {
        return [
            'count' => 0,
            'open_record_count' => 0,
            'redeemed_count' => 0,
            'expired_unredeemed_count' => 0,
            'cancelled_count' => 0,
            'persisted_state_counts' => [],
            'amount_authority' => 'treasury_pay_code_reserve_position',
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function mergePayCodeFacts(array $left, array $right): array
    {
        foreach (['count', 'open_record_count', 'redeemed_count', 'expired_unredeemed_count', 'cancelled_count'] as $key) {
            $left[$key] = (int) ($left[$key] ?? 0) + (int) ($right[$key] ?? 0);
        }

        foreach ((array) ($right['persisted_state_counts'] ?? []) as $state => $count) {
            $left['persisted_state_counts'][$state] = (
                $left['persisted_state_counts'][$state] ?? 0
            ) + (int) $count;
        }

        ksort($left['persisted_state_counts']);

        return $left;
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
