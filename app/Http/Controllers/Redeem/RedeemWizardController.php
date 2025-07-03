<?php

namespace App\Http\Controllers\Redeem;

use LBHurtado\PaymentGateway\Support\BankRegistry;
use Propaganistas\LaravelPhone\Rules\Phone;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\RedirectResponse;
use LBHurtado\Voucher\Models\Voucher;
use App\Http\Controllers\Controller;
use Inertia\{Inertia, Response};
use Illuminate\Http\Request;

class RedeemWizardController extends Controller
{
    public function mobile(Voucher $voucher): Response
    {
        $registry = new BankRegistry();

        return Inertia::render('Redeem/Form', [
            'voucher_code'    => $voucher->code,
            'country'    => config('x-change.redeem.default_country', 'PH'),
            'bank_code'  => '',
            'banks'      => collect($registry->all())
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
            'mobile' => ['required', (new Phone)->country('PH')->type('mobile')],
            'country' => ['required', 'string'],
            'bank_code' => ['nullable', 'string'],
            'account_number' => ['nullable', 'string'],
        ]);

        // Scope all entries under redeem.{voucher}
        Session::put("redeem.{$voucher->code}.mobile", $validated['mobile']);
        Session::put("redeem.{$voucher->code}.country", $validated['country']);
        Session::put("redeem.{$voucher->code}.bank_code", $validated['bank_code']);
        Session::put("redeem.{$voucher->code}.account_number", $validated['account_number']);

        return redirect()->route('redeem.finalize', $voucher);
//        return to_route('redeem.inputs', ['voucher' => $voucher]);
    }

    public function inputs(Voucher $voucher): Response
    {
        return Inertia::render('Redeem/Inputs', [
            'context' => [
                'voucherCode' => $voucher->code,
                'mobile'      => Session::get("redeem.{$voucher->code}.mobile"),
            ],
            'inputs' => Session::get("redeem.{$voucher->code}.inputs", []),
        ]);
    }

    public function storeInputs(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'name'                  => 'required|string',
            'address'               => 'required|string',
            'birthdate'             => 'required|date',
            'email'                 => 'required|email',
            'gross_monthly_income'  => 'required|numeric|min:0',
        ]);

        Session::put("redeem.{$voucher->code}.inputs", $validated);

        return redirect()->route('redeem.signature', $voucher);
    }

    public function signature(Voucher $voucher): Response
    {
        return Inertia::render('Redeem/Signature', [
            'context' => [
                'voucherCode' => $voucher->code,
                'mobile'      => Session::get("redeem.{$voucher->code}.mobile"),
            ],
        ]);
    }

    public function storeSignature(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'signature' => 'required|string',
        ]);

        Session::put("redeem.{$voucher->code}.signature", $validated['signature']);

        return redirect()->route('redeem.finalize', $voucher);
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
            'voucher' => $voucher->getData(),
            'signature' => Session::get("redeem.{$voucher->code}.signature"),
        ]);

        Session::forget([
            "redeem.{$code}.mobile",
            "redeem.{$code}.country",
            "redeem.{$code}.bank_code",
            "redeem.{$code}.account_number",
            "redeem.{$code}.inputs",
            "redeem.{$code}.signature",
        ]);

        return $response;
    }
}
