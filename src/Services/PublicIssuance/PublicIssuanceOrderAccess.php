<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicIssuanceOrderAccess
{
    public const TokenHeader = 'X-XChange-Guest-Order-Token';

    public function bind(PayCodeIssuanceFundingOrder $order, Request $request): string
    {
        $token = Str::random(64);
        $metadata = (array) $order->metadata;
        $metadata['public_auto_generate'] = [
            'token_hash' => hash('sha256', $token),
            'session_hash' => $this->sessionHash($request),
            'bound_at' => now()->toIso8601String(),
        ];

        $order->forceFill(['metadata' => $metadata])->saveQuietly();
        $request->session()->put('x-change.public-auto-generate.active-order', [
            'reference' => $order->reference,
            'token' => $token,
        ]);

        return $token;
    }

    /**
     * @return array{order: PayCodeIssuanceFundingOrder, token: string}|null
     */
    public function active(Request $request): ?array
    {
        $reference = $request->session()->get('x-change.public-auto-generate.active-order.reference');
        $token = $request->session()->get('x-change.public-auto-generate.active-order.token');

        if (! is_string($reference) || ! is_string($token)) {
            return null;
        }

        $order = PayCodeIssuanceFundingOrder::query()
            ->where('reference', $reference)
            ->whereNotIn('status', [
                PayCodeIssuanceFundingOrderStatus::Issued->value,
                PayCodeIssuanceFundingOrderStatus::Cancelled->value,
                PayCodeIssuanceFundingOrderStatus::Expired->value,
            ])
            ->first();

        if (! $order instanceof PayCodeIssuanceFundingOrder) {
            return null;
        }

        $request->headers->set(self::TokenHeader, $token);
        $this->authorize($order, $request);

        return ['order' => $order, 'token' => $token];
    }

    public function authorize(PayCodeIssuanceFundingOrder $order, Request $request): string
    {
        $token = trim((string) $request->header(self::TokenHeader));
        $tokenHash = data_get($order->metadata, 'public_auto_generate.token_hash');
        $sessionHash = data_get($order->metadata, 'public_auto_generate.session_hash');

        if (! is_string($tokenHash)
            || ! is_string($sessionHash)
            || $token === ''
            || ! hash_equals($tokenHash, hash('sha256', $token))
            || ! hash_equals($sessionHash, $this->sessionHash($request))) {
            throw new NotFoundHttpException;
        }

        return $token;
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', (string) $request->session()->getId());
    }
}
