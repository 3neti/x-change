<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\{RedirectResponse, Request};
use App\Http\Controllers\Controller;
use Inertia\{Inertia, Response};


class ChannelController extends Controller
{
    /**
     * Show the user's channel settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Channel', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's channel settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'mobile' => ['nullable', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
            'webhook' => ['nullable', 'url'],
        ]);

        $user = $request->user();

        if ($request->has('mobile')) {
            $user->mobile = $request->get('mobile');
            $user->save();
        }

        if ($request->has('webhook')) {
            $user->webhook = $request->get('webhook') ? $request->get('webhook') : '';
            $user->save();
        }

        return to_route('channel.edit');
    }
}
