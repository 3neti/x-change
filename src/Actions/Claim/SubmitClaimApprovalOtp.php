<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;
use LBHurtado\XChange\Support\Claim\NullClaimApprovalOtpAuthorizer;

final class SubmitClaimApprovalOtp
{
    public function __construct(
        private readonly ?ClaimApprovalOtpAuthorizer $authorizer = null,
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
        $authorizer = $this->authorizer;

        if (! $authorizer && app()->bound(ClaimApprovalOtpAuthorizer::class)) {
            $authorizer = app(ClaimApprovalOtpAuthorizer::class);
        }

        return ($authorizer ?? app(NullClaimApprovalOtpAuthorizer::class))
            ->authorize($voucher, $payload);
    }
}
