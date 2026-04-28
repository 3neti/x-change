<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface PayCodePresentationResolverContract
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Voucher $voucher): array;
}
