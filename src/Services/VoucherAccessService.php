<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Database\Eloquent\Builder;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Exceptions\VoucherNotRedeemable;

class VoucherAccessService implements VoucherAccessContract
{
    public function find(string|int $voucher): ?Voucher
    {
        if (is_numeric($voucher)) {
            return Voucher::query()->find((int) $voucher);
        }

        return $this->findByCode((string) $voucher);
    }

    public function findOrFail(string|int $voucher): Voucher
    {
        $model = $this->find($voucher);

        if ($model instanceof Voucher) {
            return $model;
        }

        throw new VoucherNotFound(is_scalar($voucher) ? (string) $voucher : 'voucher');
    }

    public function findByCode(string $code): ?Voucher
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return null;
        }

        return Voucher::query()
            ->where('code', $normalized)
            ->first();
    }

    public function findByCodeOrFail(string $code): Voucher
    {
        $voucher = $this->findByCode($code);

        if ($voucher instanceof Voucher) {
            return $voucher;
        }

        throw new VoucherNotFound(strtoupper(trim($code)));
    }

    public function list(array $filters = []): iterable
    {
        $query = Voucher::query();

        $this->applyStatusFilter($query, $filters);
        $this->applyIssuerFilter($query, $filters);

        return $query
            ->latest('id')
            ->get();
    }

    public function assertRedeemable(Voucher $voucher): void
    {
        if ($voucher->isExpired()) {
            throw new VoucherNotRedeemable('Voucher has expired.');
        }

        if ($voucher->isClosed()) {
            throw new VoucherNotRedeemable('Voucher is already closed.');
        }

        if ($voucher->redeemed_at !== null) {
            throw new VoucherNotRedeemable('Voucher is already redeemed.');
        }
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    protected function applyStatusFilter(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;

        if (! is_string($status) || trim($status) === '') {
            return;
        }

        $normalized = strtolower(trim($status));

        match ($normalized) {
            'redeemed' => $query->whereNotNull('redeemed_at'),
            'expired' => $query->where('expires_at', '<', now()),
            'cancelled', 'closed' => $query->where('state', 'CLOSED'),
            default => $query->where('state', strtoupper($normalized)),
        };
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    protected function applyIssuerFilter(Builder $query, array $filters): void
    {
        $issuerId = $filters['issuer_id'] ?? null;

        if (! is_numeric($issuerId)) {
            return;
        }

        // Assumes owner morph/key is available through owner_id.
        // Adjust here if your voucher package stores issuer ownership differently.
        $query->where('owner_id', (int) $issuerId);
    }
}
