<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitAccountReadModelProvider;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;
use RuntimeException;

class CockpitAccountPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly CockpitAccountReadModelProvider $accounts,
        private readonly WalletAccessContract $wallets,
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

        return Inertia::render('x-change/cockpit/Accounts', [
            ...$this->props->toArray(includeReadModel: false),
            'account_overview' => $this->accounts->forDepositor($owner, $accountReference),
        ]);
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
