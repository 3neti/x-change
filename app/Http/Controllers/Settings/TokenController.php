<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    /**
     * Show the user's token settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $cacheKey = "user:{$user->id}:token_name";

        return Inertia::render('settings/Token', [
            'token_name' => Cache::get($cacheKey, 'api-token'),
            'token' => Session::get('generated_token'),     // one-time display
            'generated_at' => Session::get('generated_at'), // one-time display
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Generate or regenerate a personal access token.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token_name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $cacheKey = "user:{$user->id}:token_name";

        // Cache the token name
        Cache::forever($cacheKey, $validated['token_name']);

        // Delete any existing token with same name
        $user->tokens()->where('name', $validated['token_name'])->delete();

        // Generate new token
        $token = $user->createToken($validated['token_name'])->plainTextToken;

        // Flash token and timestamp for display (one-time only)
        Session::flash('generated_token', $token);
        Session::flash('generated_at', now()->toISOString());

        return redirect()->route('token.edit');
    }
}
