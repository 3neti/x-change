<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Checkout;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\CheckoutViewerLink;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class CheckoutConsoleAccess
{
    public function owner(Request $request, CommercialPrincipal $principal): bool
    {
        $password = $this->configuredPassword();

        return $password !== null
            && hash_equals(
                hash_hmac('sha256', $principal->reference, $password),
                (string) $request->session()->get('x-change.checkout.owner'),
            );
    }

    public function viewer(string $token, CommercialPrincipal $principal): bool
    {
        if (strlen($token) !== 64 || ! ctype_alnum($token)) {
            return false;
        }

        return CheckoutViewerLink::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('owner_type', $principal::class)
            ->where('owner_id', (string) $principal->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function unlock(Request $request, CommercialPrincipal $principal, string $candidate): bool
    {
        $password = $this->configuredPassword();

        if ($password === null || ! hash_equals($password, $candidate)) {
            $this->audit($request, $principal, 'unlock_denied');

            return false;
        }

        $request->session()->regenerate();
        $request->session()->put('x-change.checkout.owner', hash_hmac('sha256', $principal->reference, $password));
        $this->audit($request, $principal, 'owner_unlocked');

        return true;
    }

    public function reconfirm(string $candidate): bool
    {
        $password = $this->configuredPassword();

        return $password !== null && hash_equals($password, $candidate);
    }

    public function audit(Request $request, CommercialPrincipal $principal, string $eventType): void
    {
        DB::table('x_change_checkout_console_access_events')->insert([
            'owner_type' => $principal::class,
            'owner_id' => (string) $principal->getKey(),
            'event_type' => $eventType,
            'session_hash' => hash('sha256', $request->session()->getId()),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'occurred_at' => now(),
        ]);
    }

    private function configuredPassword(): ?string
    {
        $password = config('x-change.checkout_console.owner_password');

        if (! is_string($password) || $password === '') {
            return null;
        }

        if (app()->environment('production') && (mb_strlen($password) < 16 || $password === 'password')) {
            return null;
        }

        return $password;
    }
}
