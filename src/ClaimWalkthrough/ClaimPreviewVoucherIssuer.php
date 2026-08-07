<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;

final class ClaimPreviewVoucherIssuer
{
    public function __construct(
        private readonly PayCodeIssuanceContract $issuance,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function issue(Authenticatable $issuer, array $payload): array
    {
        return Event::fakeFor(
            fn (): array => $this->issuance->issue($issuer, $payload),
            [VouchersGenerated::class],
        );
    }
}
