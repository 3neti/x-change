<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface VoucherLifecycleServiceContract
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array<int, array<string,mixed>>
     */
    public function list(array $filters = []): array;

    /**
     * @return array<string,mixed>|object|null
     */
    public function show(string $voucher): mixed;

    /**
     * @return array<string,mixed>|object|null
     */
    public function showByCode(string $code): mixed;

    /**
     * @return array<string,mixed>|object|null
     */
    public function status(string $voucher): mixed;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|object
     */
    public function cancel(string $voucher, array $payload = []): mixed;
}
