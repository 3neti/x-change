<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\BuildNetbankProfileData;
use LBHurtado\XChange\Services\BuildPaynamicsWalletProfileData;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(
        Request $request,
        BuildPaynamicsWalletProfileData $paynamicsWallets,
        BuildNetbankProfileData $netbank,
    ): Response {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'paynamicsWallet' => $paynamicsWallets->handle($request->user()),
            'netbankProfile' => $netbank->handle(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        if ($request->user()->isDirty('mobile')) {
            $request->user()->mobile_verified_at = now();
        }

        $request->user()->save();

        if (method_exists($request->user(), 'setMobileChannel')) {
            try {
                $request->user()->setMobileChannel($request->user()->getRawOriginal('mobile'));
            } catch (Throwable) {
            }
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
