<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\TransitionPayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use RuntimeException;

final readonly class ReserveFundingOrderLink
{
    public function __construct(private TransitionPayCodeIssuanceFundingOrder $transition) {}

    public function assertActive(ReserveTransfer $transfer, bool $allowAttention = false): ?PayCodeIssuanceFundingOrder
    {
        if ($transfer->funding_order_id === null) {
            return null;
        }
        $order = PayCodeIssuanceFundingOrder::query()->lockForUpdate()->findOrFail($transfer->funding_order_id);
        $owner = $transfer->owner;
        if (! in_array($order->status, $allowAttention
            ? [PayCodeIssuanceFundingOrderStatus::Issuing, PayCodeIssuanceFundingOrderStatus::IssuanceAttention]
            : [PayCodeIssuanceFundingOrderStatus::Issuing], true) || $order->funded_at === null
            || ! $owner instanceof Model || $order->issuer_type !== $owner::class
            || (string) $order->issuer_id !== (string) $owner->getKey()
            || $order->connection_reference !== $transfer->source_treasury_connection
            || data_get($order->metadata, 'funding_source.account_link_id') !== $transfer->bank_account_link_id
            || data_get($order->metadata, 'reserve_transfer_reference') !== $transfer->reference
            || $order->required_amount_minor !== $transfer->amount_minor + $transfer->fee_minor
            || $order->currency !== $transfer->currency || $order->provider !== $transfer->provider
            || $order->fundingIntent?->status !== FundingIntentStatus::Settled
            || data_get($order->metadata, 'provider_reversal') !== null
            || Checkout::query()->where('funding_order_id', $order->getKey())->whereHas('refundCase')->exists()) {
            throw new RuntimeException('The paid order is no longer eligible for reserve-backed issuance.');
        }

        $connection = app(AssignedFundingTreasuryConnection::class)->resolveForOrder($owner, $order);
        if ($connection === null || $connection->reference !== $transfer->source_treasury_connection) {
            throw new RuntimeException('The paid order Treasury binding has changed.');
        }

        return $order;
    }

    public function complete(ReserveTransfer $transfer): void
    {
        $order = $this->assertActive($transfer);
        if ($order === null) {
            return;
        }
        if ($transfer->status !== 'issued' || $transfer->voucher_id === null) {
            throw new RuntimeException('The reserve transfer has no issued Pay Code.');
        }
        $this->transition->handle($order, PayCodeIssuanceFundingOrderStatus::Issued, 'pay_code_issued', self::class,
            (string) $transfer->getKey(), ['voucher_id' => $transfer->voucher_id, 'issued_at' => now()],
            ['reserve_transfer_reference' => $transfer->reference]);
        Checkout::query()->where('funding_order_id', $order->getKey())->update(['status' => 'issued', 'updated_at' => now()]);
    }

    public function markRejected(ReserveTransfer $transfer): void
    {
        $order = $this->assertActive($transfer, allowAttention: true);
        if ($order === null) {
            return;
        }
        if ($transfer->status !== 'rejected' || ! is_array($transfer->bank_evidence_ciphertext)) {
            throw new RuntimeException('A verified reserve rejection is required before order review.');
        }
        app(DurableReserveTransfer::class)->assertFrozen($transfer);
        app(DurableReserveTransfer::class)->assertHold($transfer);
        app(ReserveIssuanceSnapshot::class)->assertFeeHold($transfer);
        if ($order->status === PayCodeIssuanceFundingOrderStatus::IssuanceAttention) {
            return;
        }
        app(ReserveTransferJournal::class)->recordPaidOrderRejection($transfer, $order);
        $this->transition->handle($order, PayCodeIssuanceFundingOrderStatus::IssuanceAttention, 'reserve_transfer_rejected', self::class,
            (string) $transfer->getKey(), ['attention_at' => now()], ['reserve_transfer_reference' => $transfer->reference]);
        Checkout::query()->where('funding_order_id', $order->getKey())->update(['status' => 'attention', 'updated_at' => now()]);
    }
}
