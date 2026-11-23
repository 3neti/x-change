<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;
use Throwable;

final readonly class SelectedAccountClientFunds
{
    public function __construct(
        private AssignedBankTreasuryConnection $assigned,
        private SharedBankTreasuryConnection $shared,
        private TreasuryAccountBalanceReadModel $balances,
        private BuildBalanceOverview $overview,
    ) {}

    /** @return array{mode: string, amount_minor: ?int, account_link_id?: int, connection_reference?: string} */
    public function forOwner(Model $owner): array
    {
        try {
            $provider = app(ProviderRuntimeSettingsResolverContract::class)->provider();
            if ($provider !== 'netbank') {
                $overview = $this->overview->handle($owner, syncIfStale: false);
                $balance = collect((array) data_get($overview, 'balances', []))->firstWhere('key', 'local_ledger');

                return ['mode' => 'provider', 'amount_minor' => is_numeric(data_get($balance, 'balance_minor')) ? (int) $balance['balance_minor'] : null];
            }

            $preference = FundingDestinationPreference::query()->whereMorphedTo('owner', $owner)
                ->where('provider_code', 'netbank')->first();

            if ($preference !== null && $preference->mode !== 'shared') {
                if ($preference->mode !== 'dedicated' || $preference->provider_account_link_id === null) {
                    return ['mode' => 'unavailable', 'amount_minor' => null];
                }
                $connection = $this->assigned->resolve($owner, (int) $preference->provider_account_link_id);
                $balance = $this->balances->connectionBalanceMinor($owner, 'netbank', $connection->reference, 'PHP');

                return [
                    'mode' => 'dedicated', 'amount_minor' => $balance ?? 0,
                    'account_link_id' => (int) $preference->provider_account_link_id,
                    'connection_reference' => $connection->reference,
                ];
            }
            $connection = $this->shared->resolveIfAccountsAssigned('netbank', 'PHP');
            $balance = $connection === null
                ? $this->balances->providerBalanceMinor($owner, 'netbank', 'PHP')
                : $this->balances->connectionBalanceMinor($owner, 'netbank', $connection->reference, 'PHP');

            return ['mode' => 'shared', 'amount_minor' => $balance ?? 0];
        } catch (Throwable) {
            return ['mode' => 'unavailable', 'amount_minor' => null];
        }
    }
}
