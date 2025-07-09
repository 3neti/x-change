<?php

namespace App\Http\Controllers\Settings;

use Laravel\WorkOS\Http\Requests\AuthKitAccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;
use App\Models\User;
use Inertia\Inertia;
use LBHurtado\PaymentGateway\Models\Merchant;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
//            'mobile' => ['nullable', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
//            'merchant_code' => ['nullable', 'string', 'min:1', 'max:1'],
//            'merchant_name' => ['nullable', 'string', 'max:255'],
//            'merchant_city' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update([
            'name' => $request->name
        ]);

//        if ($request->has('mobile')) {
//            $user->mobile = $request->get('mobile');
//            $user->save();
//        }
//
//        if ($request->has('merchant_code')) {
//            if ($merchant = $user->merchant) {
//                $merchant->update([
//                    'code' => $request->get('merchant_code'),
//                    'name' => $request->get('merchant_name'),
//                    'city' => $request->get('merchant_city'),
//                ]);
////                $merchant->save();
//            }
//        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(AuthKitAccountDeletionRequest $request): RedirectResponse
    {
        return $request->delete(
            using: fn (User $user) => $user->delete()
        );
    }
}
