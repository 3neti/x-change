<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Money\VoucherLiabilitySummaryData;
use Throwable;

class VoucherLiabilitySummaryService implements VoucherLiabilitySummaryContract
{
    public function __construct(
        private readonly WalletAccessContract $wallets,
        private readonly ?AccountBalanceReadModelContract $accountBalances = null,
    ) {}

    public function forIssuer(mixed $issuer): VoucherLiabilitySummaryData
    {
        if (! $issuer instanceof Model) {
            return new VoucherLiabilitySummaryData(
                status: 'unavailable',
                currency: $this->currency(),
            );
        }

        $walletBalanceMinor = $this->walletBalanceMinor($issuer);
        $vouchers = $this->issuerVouchers($issuer);

        $active = 0;
        $redeemed = 0;
        $expired = 0;
        $cancelled = 0;
        $outstanding = 0;
        $activeCount = 0;
        $redeemedCount = 0;
        $expiredCount = 0;
        $cancelledCount = 0;

        foreach ($vouchers as $voucher) {
            $amount = $this->voucherLiabilityMinor($voucher);
            $status = $this->status($voucher);

            if ($status === 'cancelled') {
                $cancelled += $amount;
                $cancelledCount++;

                continue;
            }

            if ($status === 'expired') {
                $expired += $amount;
                $expiredCount++;

                continue;
            }

            if ($status === 'redeemed') {
                $redeemed += $amount;
                $redeemedCount++;

                continue;
            }

            $remaining = $this->remainingClaimBalanceMinor($voucher) ?? $amount;

            $active += $remaining;
            $outstanding += $remaining;
            $activeCount++;
        }

        return new VoucherLiabilitySummaryData(
            currency: $this->currency(),
            wallet_balance_minor: $walletBalanceMinor,
            active_issued_minor: $active,
            redeemed_minor: $redeemed,
            expired_minor: $expired,
            cancelled_minor: $cancelled,
            outstanding_liability_minor: $outstanding,
            usable_balance_estimate_minor: max(0, $walletBalanceMinor - $outstanding),
            active_count: $activeCount,
            redeemed_count: $redeemedCount,
            expired_count: $expiredCount,
            cancelled_count: $cancelledCount,
        );
    }

    /**
     * @return Collection<int, Voucher>
     */
    private function issuerVouchers(Model $issuer): Collection
    {
        return Voucher::query()
            ->where('owner_id', $issuer->getKey())
            ->whereIn('owner_type', array_values(array_unique([
                $issuer::class,
                $issuer->getMorphClass(),
            ])))
            ->get();
    }

    private function status(Voucher $voucher): string
    {
        if ($voucher->state === VoucherState::CLOSED || $voucher->state === VoucherState::CANCELLED) {
            return 'cancelled';
        }

        if ($voucher->isExpired()) {
            return 'expired';
        }

        if ($voucher->redeemed_at !== null || $this->remainingClaimBalanceMinor($voucher) === 0) {
            return 'redeemed';
        }

        return 'active';
    }

    private function voucherLiabilityMinor(Voucher $voucher): int
    {
        $cash = $voucher->cash;

        if ($cash !== null) {
            $storedAmount = $cash->getRawOriginal('amount');

            if (is_numeric($storedAmount)) {
                return (int) $storedAmount;
            }

            $moneyAmount = $cash->amount;

            if (is_object($moneyAmount) && method_exists($moneyAmount, 'getMinorAmount')) {
                return $moneyAmount->getMinorAmount()->toInt();
            }
        }

        $amount = data_get($voucher->instructions->toArray(), 'cash.amount');

        if (is_numeric($amount)) {
            return (int) round(((float) $amount) * 100);
        }

        $target = $voucher->getRawOriginal('target_amount');

        if (is_numeric($target)) {
            return (int) $target;
        }

        return 0;
    }

    private function remainingClaimBalanceMinor(Voucher $voucher): ?int
    {
        $claim = $voucher->claims()
            ->reorder()
            ->orderByDesc('claim_number')
            ->first();

        if ($claim === null || $claim->remaining_balance_minor === null) {
            return null;
        }

        return max(0, (int) $claim->remaining_balance_minor);
    }

    private function walletBalanceMinor(Model $issuer): int
    {
        try {
            $positionBalance = ($this->accountBalances
                ?? app(AccountBalanceReadModelContract::class))
                ->balanceMinor($issuer, $this->currency());

            if ($positionBalance !== null) {
                return $positionBalance;
            }

            $wallet = $this->wallets->resolveForUser($issuer);

            return $this->normalizeMinor($this->wallets->getBalance($wallet));
        } catch (Throwable) {
            return 0;
        }
    }

    private function normalizeMinor(int|float|string $balance): int
    {
        if (is_int($balance)) {
            return $balance;
        }

        if (is_string($balance)) {
            $trimmed = trim($balance);

            if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }

            return (int) round(((float) $trimmed) * 100);
        }

        return (int) round($balance * 100);
    }

    private function currency(): string
    {
        return (string) config('x-change.pricing.currency', config('x-change.product.default_currency', 'PHP'));
    }
}
