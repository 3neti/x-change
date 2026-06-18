<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\LinkExistingPaynamicsWallet;

class LinkPaynamicsWalletController extends Controller
{
    public function __invoke(Request $request, LinkExistingPaynamicsWallet $wallets): RedirectResponse
    {
        $validated = $request->validate([
            'wallet_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $wallets->handle($request->user(), $validated['wallet_id']);

        return back()->with('status', 'paynamics-wallet-linked');
    }
}
