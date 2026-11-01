<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;

final class ParticipantDocumentSession
{
    public function grant(Request $request, Voucher $voucher, string $role): void
    {
        if (! $this->hasRequirement($voucher, $role)) {
            return;
        }
        $current = $request->session()->get($this->key($voucher, $role));
        $nonce = is_array($current) && ($current['actor'] ?? null) === $request->user()?->getAuthIdentifier()
            && is_int($current['until'] ?? null) && $current['until'] > now()->getTimestamp()
            && is_string($current['nonce'] ?? null) ? $current['nonce'] : Str::random(48);
        $request->session()->put($this->key($voucher, $role), [
            'until' => now()->addMinutes(30)->getTimestamp(),
            'actor' => $request->user()?->getAuthIdentifier(),
            'nonce' => $nonce,
        ]);
    }

    public function allows(Request $request, Voucher $voucher, string $role, string $type): bool
    {
        $grant = $request->session()->get($this->key($voucher, $role));
        if (data_get($voucher->metadata, 'instructions.execution.schema') !== 'voucher.execution.v2'
            || ! is_array($grant) || ! is_int($grant['until'] ?? null) || $grant['until'] <= now()->getTimestamp()
            || ! is_string($grant['nonce'] ?? null) || strlen($grant['nonce']) !== 48
            || ($grant['actor'] ?? null) !== $request->user()?->getAuthIdentifier()) {
            return false;
        }

        foreach ((array) data_get($voucher->metadata, 'instructions.execution.document_requirements', []) as $requirement) {
            if (is_array($requirement) && ($requirement['contributor'] ?? null) === $role && ($requirement['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    public function fingerprint(Request $request, Voucher $voucher, string $role): ?string
    {
        $grant = $request->session()->get($this->key($voucher, $role));
        $nonce = is_array($grant) ? ($grant['nonce'] ?? null) : null;
        $key = config('app.key');

        return is_string($nonce) && is_string($key) && trim($key) !== '' ? hash_hmac('sha256', $nonce, $key) : null;
    }

    private function hasRequirement(Voucher $voucher, string $role): bool
    {
        if (! in_array($role, ['payer', 'claimant'], true)
            || data_get($voucher->metadata, 'instructions.execution.schema') !== 'voucher.execution.v2') {
            return false;
        }
        foreach ((array) data_get($voucher->metadata, 'instructions.execution.document_requirements', []) as $requirement) {
            if (is_array($requirement) && ($requirement['contributor'] ?? null) === $role) {
                return true;
            }
        }

        return false;
    }

    private function key(Voucher $voucher, string $role): string
    {
        return 'x-change.document-grant.'.hash('sha256', (string) $voucher->getKey().':'.$role);
    }
}
