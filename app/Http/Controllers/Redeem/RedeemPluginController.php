<?php

namespace App\Http\Controllers\Redeem;

use Illuminate\Support\Facades\Session;
use Illuminate\Http\RedirectResponse;
use LBHurtado\Voucher\Models\Voucher;
use App\Support\RedeemPluginManager;
use Inertia\{Inertia, Response};
use Illuminate\Http\Request;

class RedeemPluginController
{
    public function show(Voucher $voucher, string $plugin): Response
    {
        $pluginConfig = RedeemPluginManager::get($plugin);

        abort_unless($pluginConfig && $pluginConfig['enabled'], 404);

        return Inertia::render($pluginConfig['page'], [
            'context' => [
                'voucherCode' => $voucher->code,
                'mobile' => Session::get("redeem.{$voucher->code}.mobile"),
            ],
            $pluginConfig['session_key'] => Session::get("redeem.{$voucher->code}.{$pluginConfig['session_key']}", []),
        ]);
    }

    public function store(Request $request, Voucher $voucher, string $plugin): RedirectResponse
    {
        $pluginConfig = RedeemPluginManager::get($plugin);

        abort_unless($pluginConfig && $pluginConfig['enabled'], 404);

        $validated = $request->validate($pluginConfig['validation'] ?? []);

        Session::put("redeem.{$voucher->code}.{$pluginConfig['session_key']}", $validated);

        // Figure out next step if needed. For now, always go to finalize.
        return redirect()->route('redeem.finalize', $voucher);
    }
}
