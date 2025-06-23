<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\PhoneNumber;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Actions\EncashCheck;


/** @deprecated */
class RedeemVoucherController extends Controller
{
    const META_KEY = 'redemption';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia()->render('Redeem');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string', 'exists:vouchers,code'],
            'mobile' => ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)
                ->country('PH')
                ->type('mobile')],
            'country' => ['nullable', 'string', 'size:2'],
            'bank_code' => ['nullable', 'alpha_num', 'required_with:account_number'],
            'account_number' => ['nullable', 'alpha_num', 'required_with:bank_code'],
        ]);

        // build the PhoneNumber
        $phoneNumber = new PhoneNumber(
            number: $validated['mobile'],
            country: $validated['country'] ?? 'PH'
        );

        // lookup voucher
        $voucher = Voucher::where('code', $validated['voucher_code'])
            ->firstOrFail();

        // optionally pack bank_account
        $meta = [];
        if (!empty($validated['bank_code']) && !empty($validated['account_number'])) {
            $meta = [
                'bank_account' => $validated['bank_code'] . ':' . $validated['account_number'],
            ];
        }

        try {
            $success = EncashCheck::run($voucher, $phoneNumber, $meta);
        } catch (\Throwable $e) {
            Log::error("EncashCheck failed: {$e->getMessage()}");
            $success = false;
        }

        // Prepare a message & status code
        if ($success) {
            $msg = 'Voucher redeemed successfully!';
            $status = 204;         // no content
        } else {
            $msg = 'Unable to redeem voucher.';
            $status = 422;         // or 400/500 depending on your needs
        }

        // 1️⃣ JSON/XHR clients
        if ($request->expectsJson()) {
            if ($success) {
                return response()->noContent();  // 204
            }

            return response()->json([
                'message' => $msg,
            ], $status);
        }

        // 2️⃣ Inertia visits
        if ($request->header('X-Inertia')) {
            // you could use `session()->flash()` directly or:
            return redirect()
                ->back()
                ->with($success ? 'success' : 'error', $msg);
        }

        // 3️⃣ Fallback (classic form POST)
        if ($success) {
            return response()->noContent(); // or redirect()->back()->with(...)
        }

        throw ValidationException::withMessages([
            'voucher_code' => [$msg],
        ]);
    }
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
