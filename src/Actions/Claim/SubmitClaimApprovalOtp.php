<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;
use LBHurtado\XChange\Support\Claim\ClaimApprovalOtpAuthorizerResolver;

final class SubmitClaimApprovalOtp
{
    public function __construct(
        private readonly ?ClaimApprovalOtpAuthorizer $authorizer = null,
        private readonly ?ClaimApprovalOtpAuthorizerResolver $resolver = null,
    ) {}

    /**
     * @param  array{
     *     otp: string,
     *     reference_id?: string|null,
     *     provider?: string|null
     * }  $payload
     * @return array{
     *     status: string,
     *     voucher_code: string,
     *     reference_id: string|null,
     *     provider: string|null,
     *     messages: array<int, string>
     * }
     */
    public function handle(Voucher $voucher, array $payload): array
    {
        $resolver = $this->resolver ?? app(ClaimApprovalOtpAuthorizerResolver::class);

        $payload = $resolver->normalizePayload($voucher, $payload);

        if ($this->authorizer) {
            return $this->authorizer->authorize($voucher, $payload);
        }

        return $resolver
            ->resolve($voucher, $payload)
            ->authorize($voucher, $payload);
    }
}
