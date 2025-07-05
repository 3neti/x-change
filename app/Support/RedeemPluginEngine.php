<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\Inertia;

/** @deprecated  */
class RedeemPluginEngine
{
//    public function handleDisplay(Voucher $voucher, string $pluginKey): Response
//    {
//        $plugin = $this->getPlugin($pluginKey);
//
//        return Inertia::render($plugin['page'], [
//            'context' => [
//                'voucherCode' => $voucher->code,
//                'mobile'      => Session::get("redeem.{$voucher->code}.mobile"),
//            ],
//            $plugin['session_key'] => Session::get("redeem.{$voucher->code}.{$plugin['session_key']}", []),
//        ]);
//    }
//
//    public function handleStore(Request $request, Voucher $voucher, string $pluginKey): \Illuminate\Http\RedirectResponse
//    {
//        $plugin = $this->getPlugin($pluginKey);
//
//        $validated = $request->validate($plugin['validation']);
//
//        Session::put("redeem.{$voucher->code}.{$plugin['session_key']}", $validated);
//
//        return redirect()->route($this->getNextPluginRoute($pluginKey), ['voucher' => $voucher]);
//    }
//
//    protected function getPlugin(string $pluginKey): array
//    {
//        $plugin = config("x-change.redeem.plugins.{$pluginKey}");
//
//        if (! $plugin || ! $plugin['enabled']) {
//            abort(404, "Plugin '{$pluginKey}' is disabled or not found.");
//        }
//
//        return $plugin;
//    }
//
//    protected function getNextPluginRoute(string $currentKey): string
//    {
//        $plugins = array_filter(config('x-change.redeem.plugins'), fn($p) => $p['enabled'] ?? false);
//        $keys = array_keys($plugins);
//        $currentIndex = array_search($currentKey, $keys);
//
//        if ($currentIndex === false || $currentIndex + 1 >= count($keys)) {
//            return 'redeem.finalize';
//        }
//
//        $nextKey = $keys[$currentIndex + 1];
//        return config("x-change.redeem.plugins.{$nextKey}.route", 'redeem.finalize');
//    }
}
