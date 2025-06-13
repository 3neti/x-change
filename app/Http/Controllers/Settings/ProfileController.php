<?php

namespace App\Http\Controllers\Settings;

use Laravel\WorkOS\Http\Requests\AuthKitAccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;
use App\Models\User;
use Inertia\Inertia;

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
            'mobile' => ['nullable', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
        ]);

        $user = $request->user();
        $user->update([
            'name' => $request->name
        ]);

        if ($request->has('mobile')) {
            $user->mobile = $request->get('mobile');
            $user->save();
        }

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
