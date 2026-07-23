<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

class RotateNetbankFundingToken
{
    public function __construct(
        private readonly NetbankFundingApiClient $netbank,
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(Model $owner): ProviderAccountLink
    {
        $preference = FundingDestinationPreference::query()
            ->with('providerAccountLink')
            ->whereMorphedTo('owner', $owner)
            ->where('provider_code', 'netbank')
            ->where('mode', 'dedicated')
            ->first();
        $link = $preference?->providerAccountLink;

        if (! $link instanceof ProviderAccountLink || ! $link->isReady()) {
            throw ValidationException::withMessages([
                'rotation' => 'An active dedicated NetBank destination is required.',
            ]);
        }

        $routing = (array) $link->routing_profile_ciphertext;
        $accountNumber = (string) data_get($routing, 'bank_account_number');
        $alias = (string) data_get($routing, 'vca_alias');

        if ($accountNumber === '' || $alias === '') {
            throw ValidationException::withMessages([
                'rotation' => 'The active NetBank destination has incomplete routing.',
            ]);
        }

        $token = $this->netbank->generateAliasToken($accountNumber, $alias);

        return DB::transaction(function () use ($owner, $link, $routing, $token): ProviderAccountLink {
            $link->forceFill([
                'routing_profile_ciphertext' => [
                    ...$routing,
                    'vca_alias_token' => $token,
                ],
                'verification_status' => 'verified',
                'verified_at' => now(),
                'last_synced_at' => now(),
            ])->save();

            $this->audit->log('funding.destination.token_rotated', [
                'actor' => (string) $owner->getKey(),
                'resource_type' => ProviderAccountLink::class,
                'resource_id' => $link->getKey(),
                'provider' => 'netbank',
                'display_reference' => $link->display_reference,
            ]);

            return $link->refresh();
        }, attempts: 3);
    }
}
