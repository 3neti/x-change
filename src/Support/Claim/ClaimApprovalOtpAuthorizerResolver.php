<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;

final class ClaimApprovalOtpAuthorizerResolver
{
    public function __construct(
        private readonly ClaimApprovalProviderNormalizer $normalizer,
    ) {}

    public function resolve(Voucher $voucher, array $payload): ClaimApprovalOtpAuthorizer
    {
        if (app()->bound(ClaimApprovalOtpAuthorizer::class)) {
            return app(ClaimApprovalOtpAuthorizer::class);
        }

        return app(NullClaimApprovalOtpAuthorizer::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(Voucher $voucher, array $payload): array
    {
        $provider = $this->resolveProvider($voucher, $payload);

        if ($provider !== null) {
            $payload['provider'] = $provider;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveProvider(Voucher $voucher, array $payload): ?string
    {
        return $this->normalizer->normalize(
            $payload['provider']
            ?? data_get($voucher->metadata, 'payout.provider')
            ?? data_get($voucher->metadata, 'provider')
            ?? $this->boundPayoutProvider()
            ?? config('x-change.payout.provider')
        );
    }

    private function boundPayoutProvider(): null|string|object
    {
        if (! app()->bound(PayoutProvider::class)) {
            return null;
        }

        return app(PayoutProvider::class);
    }
}
