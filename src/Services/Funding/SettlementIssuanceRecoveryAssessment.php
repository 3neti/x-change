<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use Throwable;

final readonly class SettlementIssuanceRecoveryAssessment
{
    public function __construct(private AccountBalanceReadModelContract $balances) {}

    /** @return array{required_minor: int, available_minor: ?int, shortfall_minor: ?int} */
    public function assess(PayCodeIssuanceFundingOrder $order): array
    {
        $instructions = $order->instructions_ciphertext;
        $snapshot = $order->pricing_snapshot_ciphertext;
        $principal = (int) round((float) data_get($instructions, 'cash.amount', 0) * 100);

        if (data_get($instructions, 'voucher_type') !== 'settlement' || $principal <= 0) {
            return ['required_minor' => 0, 'available_minor' => 0, 'shortfall_minor' => 0];
        }

        $charge = data_get($instructions, 'fee_bearer') === 'payer'
            ? 0
            : (int) data_get($snapshot, 'customer_charge_minor', 0);
        $required = max(0, $principal + $charge - $order->required_amount_minor);
        if ($required === 0) {
            return ['required_minor' => 0, 'available_minor' => 0, 'shortfall_minor' => 0];
        }

        if (! class_exists($order->issuer_type) || ! is_a($order->issuer_type, Model::class, true)) {
            return ['required_minor' => $required, 'available_minor' => null, 'shortfall_minor' => null];
        }

        $issuer = $order->issuer_type::query()->find($order->issuer_id);
        if (! $issuer instanceof Model) {
            return ['required_minor' => $required, 'available_minor' => null, 'shortfall_minor' => null];
        }

        try {
            if (method_exists($this->balances, 'forget')) {
                $this->balances->forget($issuer, $order->currency, $order->provider);
            }
            $available = $this->balances->providerBalanceMinor($issuer, $order->provider, $order->currency);
        } catch (Throwable $exception) {
            report($exception);
            $available = null;
        }

        return [
            'required_minor' => $required,
            'available_minor' => $available,
            'shortfall_minor' => $available === null ? null : max(0, $required - max(0, $available)),
        ];
    }
}
