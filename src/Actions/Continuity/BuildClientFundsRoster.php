<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Continuity;

use Carbon\CarbonImmutable;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Continuity\ClientFundsRosterData;
use LBHurtado\XChange\Data\Continuity\ClientFundsRosterRowData;
use LBHurtado\XChange\Services\Keepsake\KeepsakeUserModelResolver;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class BuildClientFundsRoster
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private KeepsakeUserModelResolver $users,
    ) {}

    public function handle(
        string $connectionReference,
        string $authorizationReference,
        CarbonImmutable $asOf,
    ): ClientFundsRosterData {
        $connection = $this->connections->active([$connectionReference])[0];
        $balancesByPrincipal = [];

        foreach ($this->positions->forConnection(
            $connection->provider,
            $connection->reference,
            $connection->currency,
        ) as $position) {
            if ($position->status !== 'active'
                || $position->purpose !== TreasuryPositionPurpose::ClientFunds) {
                continue;
            }

            $balancesByPrincipal[$position->principalReference] = (
                $balancesByPrincipal[$position->principalReference] ?? 0
            ) + $position->balanceMinor;
        }

        $model = $this->users->resolve();
        $instance = new $model;
        $limit = (int) config('x-change.instance_keepsake.max_users', 1_000);

        if ($model::query()->count() > $limit) {
            throw new RuntimeException('The Account roster exceeds the configured reporting limit.');
        }

        $rows = [];

        foreach ($model::query()
            ->select([$instance->getKeyName(), 'name', 'mobile'])
            ->orderBy($instance->getQualifiedKeyName())
            ->cursor() as $account) {
            $principalReference = $this->principalReferences->resolve($account);
            $rows[] = new ClientFundsRosterRowData(
                name: $this->displayValue($account->getAttribute('name')),
                mobile: $this->displayValue($account->getAttribute('mobile')),
                amountMinor: $balancesByPrincipal[$principalReference] ?? 0,
            );
        }

        return new ClientFundsRosterData(
            asOf: $asOf->utc(),
            instanceId: $this->instanceId(),
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            decimalPlaces: $connection->decimalPlaces,
            authorizationReference: $authorizationReference,
            rows: $rows,
        );
    }

    private function displayValue(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : 'unavailable';
    }

    private function instanceId(): ?string
    {
        $instanceId = trim((string) config('x-change.instance.id'));

        return $instanceId !== '' ? $instanceId : null;
    }
}
