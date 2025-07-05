<?php

namespace App\Http\Controllers\Redeem;

use LBHurtado\ModelInput\Support\InputRuleBuilder;
use LBHurtado\PaymentGateway\Support\BankRegistry;
use Illuminate\Support\Facades\{Config, Session};
use Illuminate\Http\{RedirectResponse, Request};
use Propaganistas\LaravelPhone\Rules\Phone;
use LBHurtado\Voucher\Models\Voucher;
use App\Http\Controllers\Controller;
use Inertia\{Inertia, Response};
use Illuminate\Support\Arr;

class RedeemWizardController extends Controller
{
    public function mobile(Voucher $voucher): Response
    {
        $registry = new BankRegistry();

        return Inertia::render('Redeem/Form', [
            'voucher_code' => $voucher->code,
            'country'      => config('x-change.redeem.default_country', 'PH'),
            'bank_code'    => '',
            'banks'        => collect($registry->all())
                ->map(fn ($info, $code) => [
                    'code' => $code,
                    'name' => $info['full_name'],
                ])
                ->values(),
        ]);
    }

    public function storeMobile(Request $request, Voucher $voucher): RedirectResponse
    {
        $validated = $request->validate([
            'mobile'         => ['required', (new Phone)->country('PH')->type('mobile')],
            'country'        => ['required', 'string'],
            'bank_code'      => ['nullable', 'string'],
            'account_number' => ['nullable', 'string'],
        ]);

        Session::put("redeem.{$voucher->code}.mobile", $validated['mobile']);
        Session::put("redeem.{$voucher->code}.country", $validated['country']);
        Session::put("redeem.{$voucher->code}.bank_code", $validated['bank_code']);
        Session::put("redeem.{$voucher->code}.account_number", $validated['account_number']);

        $plugins = Config::get('x-change.redeem.plugins', []);
        $enabledPlugins = collect($plugins)->filter(fn ($p) => $p['enabled']);
        $firstPlugin = $enabledPlugins->keys()->first();

        return $firstPlugin
            ? redirect()->route("redeem.{$firstPlugin}", ['voucher' => $voucher, 'plugin' => $firstPlugin] )
            : redirect()->route('redeem.finalize', $voucher);
    }

    public function plugin(Voucher $voucher, string $plugin): Response
    {
        $config = config("x-change.redeem.plugins.$plugin");

        abort_unless($config && $config['enabled'], 404);

        return Inertia::render($config['page'], [
            'context' => [
                'voucherCode' => $voucher->code,
                'mobile'      => Session::get("redeem.{$voucher->code}.mobile"),
            ],
            $config['session_key'] => Session::get("redeem.{$voucher->code}.{$config['session_key']}", $voucher->instructions->inputs->toArray()),
        ]);
    }

    //TODO: check this out - not working
    public function storePlugin(Request $request, Voucher $voucher, string $plugin): RedirectResponse
    {
        $plugins = collect(config('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->values();

        $config = config("x-change.redeem.plugins.$plugin");
        abort_unless($config && $config['enabled'], 404);

        // ✅ Dynamically build rules from voucher's instructions
        $rules = InputRuleBuilder::from($voucher->instructions->inputs);

        // ✅ Only validate fields that are actually present in the request
        $filteredRules = Arr::only($rules, array_keys(array_filter($request->all())));

        $validated = $request->validate($filteredRules);

        $sessionKey = $config['session_key'];

        // 🔐 Store validated input
        if (count($validated) === 1) {
            Session::put("redeem.{$voucher->code}.{$sessionKey}", reset($validated));
        } else {
            Session::put("redeem.{$voucher->code}.{$sessionKey}", $validated);
        }

        // ➡️ Continue to next plugin (or finalize)
        $currentIndex = $plugins->search($plugin);
        $nextPlugin = $plugins->get($currentIndex + 1);

        return $nextPlugin
            ? redirect()->route("redeem.$nextPlugin", ['voucher' => $voucher, 'plugin' => $nextPlugin])
            : redirect()->route('redeem.finalize', ['voucher' => $voucher]);
    }

    public function finalize(Voucher $voucher): Response
    {
        $code = $voucher->code;
        $registry = new BankRegistry();

        $bankCode = Session::get("redeem.{$code}.bank_code");
        $accountNumber = Session::get("redeem.{$code}.account_number");

        $bankAccount = (!empty($bankCode) && !empty($accountNumber)) ? (function () use ($registry, $bankCode, $accountNumber) {
            $bank = $registry->find($bankCode);
            $bankName = $bank['full_name'] ?? $bankCode;
            return "{$bankName} ({$accountNumber})";
        })() : null;

        return Inertia::render('Redeem/Finalize', [
            'voucher'      => $voucher->getData(),
            'mobile'       => Session::get("redeem.{$code}.mobile"),
            'bank_account' => $bankAccount,
        ]);
    }

    public function success(Voucher $voucher): Response
    {
        $code = $voucher->code;

        $response = Inertia::render('Redeem/Success', [
            'voucher'   => $voucher->getData(),
            'signature' => Session::get("redeem.{$voucher->code}.signature.signature"),
        ]);

        // Clear all redeem session keys
        Session::forget(collect(Config::get('x-change.plugins', []))
            ->keys()
            ->map(fn ($key) => "redeem.{$code}.{$key}")
            ->merge([
                "redeem.{$code}.mobile",
                "redeem.{$code}.country",
                "redeem.{$code}.bank_code",
                "redeem.{$code}.account_number",
            ])
            ->toArray());

        return $response;
    }
}
