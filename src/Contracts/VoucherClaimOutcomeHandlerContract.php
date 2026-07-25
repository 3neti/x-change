<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Voucher\Models\Voucher;

interface VoucherClaimOutcomeHandlerContract
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Voucher $voucher,
        array $payload,
        ?Authenticatable $claimant = null,
    ): mixed;
}
