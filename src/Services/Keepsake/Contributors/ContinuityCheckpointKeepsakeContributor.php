<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake\Contributors;

use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;

final readonly class ContinuityCheckpointKeepsakeContributor implements InstanceKeepsakeContributor
{
    public function __construct(private CanonicalKeepsakeJson $json) {}

    public function key(): string
    {
        return 'continuity';
    }

    public function snapshotSchemaVersion(): int
    {
        return 1;
    }

    public function blueprintSchemaVersion(): ?int
    {
        return null;
    }

    public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution
    {
        if (! $context->includes('continuity')) {
            return new InstanceKeepsakeContribution($this->key(), 1, null);
        }

        $snapshots = [];
        $maximumAgeSeconds = (int) config('x-change.funding.provider_balance_max_age_seconds', 300);
        $query = ProviderBalanceSnapshot::query()
            ->select([
                'provider_code', 'balance_key', 'scope_key', 'balance_minor',
                'available_balance_minor', 'currency', 'account_reference_masked',
                'provider_as_of', 'fetched_at', 'refresh_status', 'updated_at',
            ])
            ->orderBy('provider_code')
            ->orderBy('balance_key')
            ->orderBy('scope_key');

        foreach ($query->cursor() as $snapshot) {
            if (count($snapshots) >= (int) config('x-change.instance_keepsake.max_provider_checkpoints', 100)) {
                throw new InstanceKeepsakeException('limit_exceeded', 'The keepsake provider-checkpoint limit was exceeded.');
            }

            $isStale = $snapshot->refresh_status !== 'fresh'
                || $snapshot->fetched_at === null
                || $snapshot->fetched_at->lessThan(now()->subSeconds($maximumAgeSeconds));

            $snapshots[] = [
                'provider_code' => (string) $snapshot->provider_code,
                'balance_key' => (string) $snapshot->balance_key,
                'scope_key' => (string) $snapshot->scope_key,
                'balance_minor' => $snapshot->balance_minor,
                'available_balance_minor' => $snapshot->available_balance_minor,
                'currency' => (string) $snapshot->currency,
                'account_reference_masked' => $snapshot->account_reference_masked,
                'provider_as_of' => $snapshot->provider_as_of?->toIso8601String(),
                'fetched_at' => $snapshot->fetched_at?->toIso8601String(),
                'refresh_status' => (string) $snapshot->refresh_status,
                'is_stale' => $isStale,
                'maximum_age_seconds' => $maximumAgeSeconds,
                'updated_at' => $snapshot->updated_at?->toIso8601String(),
                'authority' => 'observational_snapshot',
                'restoration_authority' => false,
            ];
        }

        return new InstanceKeepsakeContribution(
            key: $this->key(),
            snapshotSchemaVersion: 1,
            blueprintSchemaVersion: null,
            snapshotFiles: [
                'snapshot/continuity-checkpoint.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.continuity-checkpoint.v1',
                    'source_instance' => [
                        'id' => filled(config('x-change.instance.id'))
                            ? trim((string) config('x-change.instance.id'))
                            : null,
                        'name' => (string) config('app.name'),
                        'url' => (string) config('app.url'),
                        'deployment_profile' => (string) config('x-change.deployment.profile'),
                        'runtime_tier' => (string) config('x-change.deployment.runtime_tier'),
                    ],
                    'observed_at' => $context->observedAt,
                    'provider_calls' => false,
                    'ownership_authority' => false,
                    'provider_balance_snapshots' => $snapshots,
                ]),
            ],
            summary: [
                'source_instance_identified' => filled(config('x-change.instance.id')),
                'provider_checkpoints' => count($snapshots),
            ],
        );
    }
}
