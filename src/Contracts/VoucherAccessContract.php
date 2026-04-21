<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface VoucherAccessContract
{
    public function find(string|int $voucher): ?Voucher;

    public function findOrFail(string|int $voucher): Voucher;

    public function findByCode(string $code): ?Voucher;

    public function findByCodeOrFail(string $code): Voucher;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<Voucher>
     */
    public function list(array $filters = []): iterable;

    public function assertRedeemable(Voucher $voucher): void;
}
