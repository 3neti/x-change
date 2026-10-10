<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PublicIssuanceTurnstileVerifier
{
    public function enabled(): bool
    {
        return (bool) config('x-change.public_auto_generate.turnstile.enabled', false);
    }

    public function siteKey(): ?string
    {
        $siteKey = config('x-change.public_auto_generate.turnstile.site_key');

        return is_string($siteKey) && trim($siteKey) !== '' ? $siteKey : null;
    }

    public function verify(Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $secret = config('x-change.public_auto_generate.turnstile.secret_key');

        if ($this->siteKey() === null || ! is_string($secret) || trim($secret) === '') {
            abort(503, 'Public issuance verification is unavailable.');
        }

        $token = $request->header('X-Turnstile-Token');

        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            $this->reject();
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(2)
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (Throwable) {
            abort(503, 'Public issuance verification is temporarily unavailable.');
        }

        if (! $response->successful()) {
            abort(503, 'Public issuance verification is temporarily unavailable.');
        }

        $expectedHostname = config('x-change.public_auto_generate.turnstile.hostname');
        $hostname = is_string($expectedHostname) && trim($expectedHostname) !== ''
            ? $expectedHostname
            : $request->getHost();

        if ($response->json('success') !== true
            || $response->json('hostname') !== $hostname
            || $response->json('action') !== 'public_auto_generate') {
            $this->reject();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'turnstile' => ['Please complete the verification and try again.'],
        ]);
    }
}
