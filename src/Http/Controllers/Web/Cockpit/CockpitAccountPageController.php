<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Cockpit\CockpitAccountReadModelProvider;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;
use RuntimeException;

class CockpitAccountPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly CockpitAccountReadModelProvider $accounts,
        private readonly WalletAccessContract $wallets,
        private readonly CockpitTreasuryAccessContract $access,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request): Response
    {
        $owner = $request->user();

        if ($owner === null) {
            throw new AuthenticationException;
        }

        $wallet = $this->wallets->resolveForUser($owner);
        $accountReference = $this->accountReference($wallet);
        Inertia::encryptHistory();
        $canConfigure = $this->access->canManageTreasuryReconciliation($owner);

        return Inertia::render('x-change/cockpit/Accounts', [
            ...$this->props->toArray(includeReadModel: false),
            'account_overview' => $this->accounts->forDepositor($owner, $accountReference),
            'bank_accounts' => $this->bankAccounts($owner, $canConfigure),
            'can_assign_bank_accounts' => $canConfigure,
            'personal_netbank_funding_enabled' => $canConfigure && (bool) config('x-change.funding.dedicated_netbank_vca_enabled', false),
            'personal_netbank_fresh_commissioning_available' => $canConfigure && app()->environment(['local', 'testing'])
                && (bool) config('x-change.funding.dedicated_netbank_vca_enabled', false),
            'treasury_connections' => $canConfigure ? $this->availableTreasuryConnections() : [],
        ]);
    }

    /** @return list<string> */
    private function availableTreasuryConnections(): array
    {
        try {
            $shared = (array) config('x-change.funding.providers.netbank.treasury', []);

            return collect(app(TreasuryProviderConnectionCatalog::class)->active())
                ->filter(static fn ($connection): bool => $connection->provider === 'netbank'
                    && $connection->currency === 'PHP'
                    && $connection->inventoryReference !== ($shared['inventory_reference'] ?? null)
                    && $connection->settlementResourceReference !== ($shared['settlement_resource_reference'] ?? null))
                ->map(static fn ($connection): string => $connection->reference)->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function bankAccounts(Model $owner, bool $canConfigure): array
    {
        $query = BankAccountAssignment::query()->with(['providerAccountLink', 'owner'])->orderBy('id');
        if (! $canConfigure) {
            $query->whereMorphedTo('owner', $owner);
        }

        $assignments = $query->get();
        $preferences = FundingDestinationPreference::query()->where('mode', 'dedicated')
            ->whereIn('owner_id', $assignments->pluck('owner_id'))->get()
            ->keyBy(static fn (FundingDestinationPreference $preference): string => $preference->owner_type.':'.$preference->owner_id.':'.$preference->provider_code);

        return $assignments->map(function (BankAccountAssignment $assignment) use ($canConfigure, $preferences, $owner): array {
            $assignedOwner = $assignment->owner;
            $details = $assignment->details_ciphertext;
            $link = $assignment->providerAccountLink;
            $ready = $link !== null && $link->isReady();
            $treasuryReady = false;
            if ($ready && $link !== null && $assignedOwner instanceof Model) {
                try {
                    app(AssignedBankTreasuryConnection::class)->resolve($assignedOwner, (int) $link->getKey());
                    $treasuryReady = true;
                } catch (RuntimeException) {
                    $treasuryReady = false;
                }
            }
            $preference = $preferences->get($assignment->owner_type.':'.$assignment->owner_id.':'.$assignment->provider);

            return [
                'id' => $assignment->getKey(),
                'owner_id' => $canConfigure ? (string) $assignment->owner_id : null,
                'is_owner' => $assignment->owner_type === $owner->getMorphClass() && (string) $assignment->owner_id === (string) $owner->getKey(),
                'provider' => $assignment->provider,
                'nickname' => $details['nickname'] ?? null,
                'account_name' => $details['account_name'],
                'account_number' => $details['provider_account_number'] ?? $details['account_number'],
                'is_default' => $link !== null && (string) $preference?->provider_account_link_id === (string) $link->getKey(),
                'funding_ready' => ($assignment->provider !== 'netbank' || (bool) config('x-change.funding.dedicated_netbank_vca_enabled', false)) && $ready && $treasuryReady && in_array('funding', $link->capabilities ?? [], true),
                'disbursement_ready' => $ready && $treasuryReady && in_array('disbursement', $link->capabilities ?? [], true),
            ];
        })->all();
    }

    private function accountReference(mixed $wallet): string
    {
        $uuid = data_get($wallet, 'uuid');

        if (is_string($uuid) && trim($uuid) !== '') {
            return 'wallet:'.trim($uuid);
        }

        if (is_object($wallet) && method_exists($wallet, 'getKey')) {
            return 'wallet:'.$wallet->getKey();
        }

        throw new RuntimeException('Funding Account reference could not be resolved.');
    }
}
