<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Repositories;

use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Models\ProviderAccountLink;

class EloquentProviderAccountLinkRepository implements ProviderAccountLinkRepositoryContract
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function storeFromProvisioningResult(mixed $owner, array $result): ProviderAccountLink
    {
        $provider = (string) data_get($result, 'provider');
        $mode = data_get($result, 'mode');
        $status = (string) data_get($result, 'status', 'pending');

        return ProviderAccountLink::query()->updateOrCreate(
            [
                'owner_type' => $owner::class,
                'owner_id' => $owner->getKey(),
                'provider' => $provider,
                'mode' => $mode,
            ],
            [
                'topology' => (string) data_get($result, 'topology'),
                'purpose' => data_get($result, 'purpose'),
                'emi_provider_account_id' => data_get($result, 'emi_provider_account_id'),
                'emi_wallet_id' => data_get($result, 'emi_wallet_id'),
                'emi_bank_account_id' => data_get($result, 'emi_bank_account_id'),
                'provider_account_id' => data_get($result, 'provider_account_id'),
                'provider_wallet_id' => data_get($result, 'provider_wallet_id'),
                'provider_bank_account_id' => data_get($result, 'provider_bank_account_id'),
                'external_uid' => data_get($result, 'external_uid'),
                'status' => $status,
                'verification_status' => data_get($result, 'verification_status'),
                'identity_level' => data_get($result, 'identity_level'),
                'capabilities' => (array) data_get($result, 'capabilities', []),
                'metadata' => $this->redactedMetadata((array) data_get($result, 'metadata', [])),
                'ready_at' => $status === 'ready'
                    ? (data_get($result, 'ready_at') ?? now())
                    : null,
                'last_synced_at' => now(),
            ],
        );
    }

    public function findReadyForOwner(mixed $owner, string $provider, ?string $mode = null): ?ProviderAccountLink
    {
        return $this->queryForOwner($owner, $provider, $mode)
            ->where('status', 'ready')
            ->whereNotNull('ready_at')
            ->latest()
            ->first();
    }

    public function findLatestForOwner(mixed $owner, string $provider, ?string $mode = null): ?ProviderAccountLink
    {
        return $this->queryForOwner($owner, $provider, $mode)
            ->latest()
            ->first();
    }

    protected function queryForOwner(mixed $owner, string $provider, ?string $mode = null): Builder
    {
        return ProviderAccountLink::query()
            ->whereMorphedTo('owner', $owner)
            ->where('provider', strtolower($provider))
            ->when($mode !== null, fn (Builder $query): Builder => $query->where('mode', $mode));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function redactedMetadata(array $metadata): array
    {
        foreach (['password', 'secret', 'client_secret', 'merchant_key', 'integration_key', 'otp'] as $key) {
            if (array_key_exists($key, $metadata)) {
                $metadata[$key] = '[redacted]';
            }
        }

        return $metadata;
    }
}
