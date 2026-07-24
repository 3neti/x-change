<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\LinkExistingPaynamicsWallet;

class UpdateFundingDestination
{
    public function __construct(
        private readonly LinkExistingPaynamicsWallet $paynamics,
        private readonly AuditLoggerContract $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $owner, string $provider, array $data): FundingDestinationPreference
    {
        $provider = $this->providerCode($provider);
        $mode = (string) data_get($data, 'mode');

        if ($mode === 'shared') {
            return $this->selectShared($owner, $provider);
        }

        $link = match ($provider) {
            'netbank' => $this->connectNetbank($owner, $data),
            'paynamics_constellation' => $this->connectPaynamics($owner, $data),
            default => throw ValidationException::withMessages([
                'provider' => 'This funding provider is unsupported.',
            ]),
        };

        return DB::transaction(function () use ($owner, $provider, $link): FundingDestinationPreference {
            $preference = $this->preference($owner, $provider);
            $preference->forceFill([
                'mode' => 'dedicated',
                'provider_account_link_id' => $link->getKey(),
                'version' => $preference->exists ? $preference->version + 1 : 1,
                'changed_by_type' => $owner::class,
                'changed_by_id' => (string) $owner->getKey(),
            ])->save();

            $this->audit->log('funding.destination.selected', [
                'actor' => (string) $owner->getKey(),
                'resource_type' => FundingDestinationPreference::class,
                'resource_id' => $preference->getKey(),
                'provider' => $provider,
                'mode' => 'dedicated',
                'display_reference' => $link->display_reference,
                'verification_status' => $link->verification_status,
            ]);

            return $preference->refresh();
        }, attempts: 3);
    }

    private function selectShared(Model $owner, string $provider): FundingDestinationPreference
    {
        return DB::transaction(function () use ($owner, $provider): FundingDestinationPreference {
            $preference = $this->preference($owner, $provider);
            $preference->forceFill([
                'mode' => 'shared',
                'provider_account_link_id' => null,
                'version' => $preference->exists ? $preference->version + 1 : 1,
                'changed_by_type' => $owner::class,
                'changed_by_id' => (string) $owner->getKey(),
            ])->save();

            $this->audit->log('funding.destination.selected', [
                'actor' => (string) $owner->getKey(),
                'resource_type' => FundingDestinationPreference::class,
                'resource_id' => $preference->getKey(),
                'provider' => $provider,
                'mode' => 'shared',
            ]);

            return $preference->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function connectNetbank(Model $owner, array $data): ProviderAccountLink
    {
        $accountNumber = (string) data_get($data, 'account_number');
        $accountName = (string) data_get($data, 'account_name');
        $alias = (string) data_get($data, 'vca_alias');
        $fingerprint = hash('sha256', "netbank|{$accountNumber}|{$alias}");

        $link = ProviderAccountLink::query()
            ->whereMorphedTo('owner', $owner)
            ->where('provider', 'netbank')
            ->where('routing_fingerprint', $fingerprint)
            ->whereNull('disabled_at')
            ->firstOrNew();

        $link->forceFill([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'provider' => 'netbank',
            'topology' => 'dedicated',
            'purpose' => 'funding',
            'mode' => 'bank_account_link',
            'status' => 'ready',
            'verification_status' => 'routing_configured',
            'identity_level' => 'corporate_account_vca',
            'capabilities' => ['funding', 'vca'],
            'metadata' => [
                'registration_token_lifecycle' => 'ephemeral_per_vca',
            ],
            'routing_profile_ciphertext' => [
                'bank_account_number' => $accountNumber,
                'bank_account_name' => $accountName,
                'vca_alias' => $alias,
            ],
            'routing_fingerprint' => $fingerprint,
            'display_reference' => '•••• '.Str::substr($accountNumber, -4)." · VCA {$alias}",
            'ready_at' => now(),
            'verified_at' => null,
            'activated_at' => now(),
            'last_synced_at' => now(),
        ])->save();

        return $link->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function connectPaynamics(Model $owner, array $data): ProviderAccountLink
    {
        $link = $this->paynamics->handle($owner, (string) data_get($data, 'wallet_id'));
        $walletId = (string) $link->provider_wallet_id;
        $link->forceFill([
            'purpose' => 'funding',
            'display_reference' => '•••• '.Str::substr($walletId, -6),
            'routing_fingerprint' => hash('sha256', "paynamics_constellation|{$walletId}"),
            'activated_at' => null,
        ])->save();

        return $link->refresh();
    }

    private function preference(Model $owner, string $provider): FundingDestinationPreference
    {
        return FundingDestinationPreference::query()->firstOrNew([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'provider_code' => $provider,
        ]);
    }

    private function providerCode(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'paynamics' => 'paynamics_constellation',
            default => strtolower(trim($provider)),
        };
    }
}
