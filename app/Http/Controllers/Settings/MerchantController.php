<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\{RedirectResponse, Request};
use App\Http\Controllers\Controller;
use Inertia\{Inertia, Response};


class MerchantController extends Controller
{
    /**
     * Show the user's channel settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Merchant', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's channel settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'merchant_code' => ['required', 'string', 'min:1', 'max:8'],
            'merchant_name' => ['required', 'string', 'max:255'],
            'merchant_city' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if ($merchant = $user->merchant) {
            $merchant->update([
                'code' => $request->get('merchant_code'),
                'name' => $request->get('merchant_name'),
                'city' => $request->get('merchant_city'),
            ]);
        }

        return to_route('merchant.edit');
    }
}
