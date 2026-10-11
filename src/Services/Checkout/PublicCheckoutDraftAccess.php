<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Checkout;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class PublicCheckoutDraftAccess
{
    public const TokenHeader = 'X-XChange-Guest-Checkout-Token';

    /** @param array<string, mixed> $instructions
     * @return array{checkout: Checkout, token: string}
     */
    public function save(Request $request, CommercialPrincipal $principal, array $instructions): array
    {
        $encoded = json_encode($instructions, JSON_THROW_ON_ERROR);

        if (strlen($encoded) > 32_768) {
            throw ValidationException::withMessages(['checkout' => ['The draft is too large.']]);
        }

        $active = $this->active($request, $principal);
        $draftPricing = data_get($instructions, '_pricing');
        $draftPricing = is_array($draftPricing) ? $draftPricing : null;

        if ($active !== null) {
            if (! hash_equals($active['token'], trim((string) $request->header(self::TokenHeader)))) {
                abort(404);
            }

            $active['checkout']->forceFill([
                'instructions_ciphertext' => $instructions,
                'pricing_snapshot_ciphertext' => $draftPricing,
                'draft_expires_at' => now()->addDays(7),
            ])->save();

            return $active;
        }

        $token = Str::random(64);
        $checkout = Checkout::query()->create([
            'source' => 'public.auto-generate',
            'owner_type' => $principal::class,
            'owner_id' => (string) $principal->getKey(),
            'status' => 'draft',
            'selected_method' => 'qr_ph',
            'guest_token_hash' => hash('sha256', $token),
            'session_hash' => $this->sessionHash($request),
            'draft_expires_at' => now()->addDays(7),
            'instructions_ciphertext' => $instructions,
            'pricing_snapshot_ciphertext' => $draftPricing,
        ]);
        $request->session()->put('x-change.checkout.draft', [
            'reference' => $checkout->reference,
            'token' => $token,
        ]);

        return ['checkout' => $checkout, 'token' => $token];
    }

    /** @return array{checkout: Checkout, token: string}|null */
    public function active(Request $request, CommercialPrincipal $principal): ?array
    {
        $reference = $request->session()->get('x-change.checkout.draft.reference');
        $token = $request->session()->get('x-change.checkout.draft.token');

        if (! is_string($reference) || ! is_string($token)) {
            return null;
        }

        $checkout = Checkout::query()
            ->where('reference', $reference)
            ->where('source', 'public.auto-generate')
            ->where('owner_type', $principal::class)
            ->where('owner_id', (string) $principal->getKey())
            ->where('status', 'draft')
            ->whereNull('funding_order_id')
            ->where('draft_expires_at', '>', now())
            ->first();

        if (! $checkout instanceof Checkout
            || ! hash_equals((string) $checkout->guest_token_hash, hash('sha256', $token))
            || ! hash_equals((string) $checkout->session_hash, $this->sessionHash($request))) {
            return null;
        }

        return ['checkout' => $checkout, 'token' => $token];
    }

    public function clear(Request $request): void
    {
        $request->session()->forget('x-change.checkout.draft');
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
