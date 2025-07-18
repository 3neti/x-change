<?php

namespace App\Http\Controllers\Redeem;

use App\Support\{RedeemPluginMap, RedeemPluginSelector};
use Illuminate\Support\Facades\{Config, Log, Session};
use LBHurtado\ModelInput\Support\InputRuleBuilder;
use LBHurtado\PaymentGateway\Support\BankRegistry;
use Illuminate\Http\{RedirectResponse, Request};
use LBHurtado\Voucher\Enums\VoucherInputField;
use App\Http\Requests\WalletFormRequest;
use LBHurtado\Voucher\Models\Voucher;
use App\Http\Controllers\Controller;
use Inertia\{Inertia, Response};
use Illuminate\Support\Arr;

class RedeemWizardController extends Controller
{
    public function wallet(Voucher $voucher): Response
    {
        return Inertia::render('Redeem/Wallet', [
            'voucher_code' => $voucher->code,
            'country' => config('x-change.redeem.default_country', 'PH'),
            'bank_code' => '',
            'banks' => $this->getNormalizedBanks(),
            'hasSecret' => (booL) $voucher->cash->secret
        ]);
    }

    public function storeWallet(WalletFormRequest $request, Voucher $voucher): RedirectResponse
    {
        $this->storeWalletData($request, $voucher);

        $plugins = RedeemPluginSelector::fromVoucher($voucher);
        Session::put("redeem.{$voucher->code}.plugins", $plugins->all());

        return ($plugin = $plugins->first())
            ? redirect()->route("redeem.{$plugin}", ['voucher' => $voucher, 'plugin' => $plugin])
            : redirect()->route('redeem.finalize', $voucher);
    }

    public function plugin(Voucher $voucher, string $plugin): Response
    {
        $config = config("x-change.redeem.plugins.$plugin");

        abort_unless($config && $config['enabled'], 404);

        // 🧭 Step 1: Get the plugin-relevant input fields
        $pluginFields = RedeemPluginMap::fieldsFor($plugin); // array<VoucherInputField>
        $pluginFieldKeys = array_map(fn(VoucherInputField $f) => $f->value, $pluginFields);

        // 🎯 Step 2: Intersect with what the voucher actually requires
        $voucherFieldKeys = array_map(
            fn(VoucherInputField $f) => $f->value,
            $voucher->instructions->inputs->fields
        );

        $requestedFields = array_values(array_intersect($pluginFieldKeys, $voucherFieldKeys));

        // 🧠 Step 3: Hydrate default values from session
        $defaultValues = collect($requestedFields)
            ->mapWithKeys(fn($field) => [
                $field => Session::get("redeem.{$voucher->code}.{$config['session_key']}")[$field] ?? null
            ])
            ->all();

        Log::info('[RedeemWizardController] Rendering plugin page', [
            'voucher' => $voucher->code,
            'plugin' => $plugin,
            'session_key' => $config['session_key'],
            'requestedFields' => $requestedFields,
            'default_values' => $defaultValues,
        ]);

        return Inertia::render($config['page'], [
            'context' => [
                'voucherCode' => $voucher->code,
                'mobile' => Session::get("redeem.{$voucher->code}.mobile"),
            ],
            $config['session_key'] => $defaultValues,
        ]);
    }

    public function storePlugin(Request $request, Voucher $voucher, string $plugin): RedirectResponse
    {
        $plugins = collect(Session::get("redeem.{$voucher->code}.plugins", []));

        $config = config("x-change.redeem.plugins.$plugin");
        abort_unless($config && $config['enabled'], 404);

        // 🧠 Step 1: Get fields associated with this plugin
        $pluginFields = RedeemPluginMap::fieldsFor($plugin);
        $pluginFieldKeys = array_map(fn(VoucherInputField $f) => $f->value, $pluginFields);

        // ✅ Step 2: Dynamically build full rules from voucher’s instructions
        $rules = InputRuleBuilder::from($voucher->instructions->inputs);

        // 🧼 Step 3: Filter rules to plugin-specific fields only
        $filteredRules = Arr::only($rules, $pluginFieldKeys);

        // 🧪 Step 4: Validate only the present fields
        $validated = $request->validate($filteredRules);

        $sessionKey = $config['session_key'];

        // 🔐 Step 5: Store validated values in session
        Session::put("redeem.{$voucher->code}.{$sessionKey}", $validated);

        Log::debug('[RedeemWizardController] Stored plugin inputs', [
            'voucher' => $voucher->code,
            'plugin' => $plugin,
            'session_key' => $sessionKey,
            'validated' => $validated,
        ]);

        // ⏭️ Step 6: Redirect to next plugin or finalize
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
            'voucher' => $voucher->getData(),
            'mobile' => Session::get("redeem.{$code}.mobile"),
            'bank_account' => $bankAccount,
        ]);
    }

    public function success(Voucher $voucher): Response
    {
        $code = $voucher->code;

        $response = Inertia::render('Redeem/Success', [
            'voucher' => $voucher->getData(),
            'inputs' => $voucher->inputs->pluck('value', 'name')->toArray(),
        ]);

        // Clear all redeem session keys
        Session::forget(collect(Config::get('x-change.plugins', []))
            ->keys()
            ->map(fn($key) => "redeem.{$code}.{$key}")
            ->merge([
                "redeem.{$code}.mobile",
                "redeem.{$code}.country",
                "redeem.{$code}.bank_code",
                "redeem.{$code}.account_number",
            ])
            ->toArray());

        return $response;
    }

    /**
     * @param WalletFormRequest $request
     * @param Voucher $voucher
     * @return void
     */
    protected function storeWalletData(WalletFormRequest $request, Voucher $voucher): void
    {
        $validated = $request->validated();

        Session::put("redeem.{$voucher->code}.mobile", $validated['mobile']);
        Session::put("redeem.{$voucher->code}.country", $validated['country']);
        Session::put("redeem.{$voucher->code}.bank_code", $validated['bank_code'] ?? null);
        Session::put("redeem.{$voucher->code}.account_number", $validated['account_number'] ?? null);
        Session::put("redeem.{$voucher->code}.secret", $validated['secret'] ?? null);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    protected function getNormalizedBanks(): \Illuminate\Support\Collection
    {
        $registry = new BankRegistry();

        return collect($registry->all())
            ->map(function ($info, $code) {
                return [
                    'code' => $code,
                    'name' => trim($info['full_name']),
                ];
            })
            ->values();
    }
}
//    protected function getNormalizedBanks(): \Illuminate\Support\Collection
//    {
//        $registry = new BankRegistry();
//
//        $wordMap = collect([
//            'of',
//            'de',
//            'AllBank',
//            'Al-Amanah',
//            'AliPay',
//            'BananaPay',
//            'BDO',
//            '(BDO)',
//            'BPI',
//            '(BPI)',
//            'BRBDigital',
//            'CTBC',
//            'DCPay',
//            'DM',
//            'GCash',
//            'GM',
//            'GoTyme',
//            'GrabPay',
//            'G-Xchange',
//            'HSBC',
//            'HK',
//            'ING',
//            'N.V.',
//            'PayMaya',
//            'RCBC',
//            'UBP',
//        ])->mapWithKeys(fn ($word) => [Str::lower($word) => $word]);
//
//        return collect($registry->all())
//            ->map(function ($info, $code) use ($wordMap) {
//                $words = explode(' ', $info['full_name']);
//
//                $normalized = collect($words)->map(function ($word) use ($wordMap) {
//                    preg_match('/^(\w+)([\W]*)$/u', $word, $matches);
//                    $base = Str::lower($matches[1] ?? $word);
//                    $punct = $matches[2] ?? '';
//
//                    $formatted = $wordMap[$base] ?? ucfirst($base);
//
//                    return $formatted . $punct;
//                })->implode(' ');
//
//                return [
//                    'code' => $code,
//                    'name' => $normalized,
//                ];
//            })
//            ->values();
//    }
//}
