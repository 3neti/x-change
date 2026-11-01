<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;

final class TransitionPayCodeIssuanceFundingOrder
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        PayCodeIssuanceFundingOrder $order,
        PayCodeIssuanceFundingOrderStatus $status,
        string $eventType,
        string $actorType,
        string $actorId,
        array $attributes = [],
        array $metadata = [],
        bool $approvedPreExpiryPaymentRecovery = false,
        bool $approvedFundedCancellation = false,
    ): PayCodeIssuanceFundingOrder {
        return DB::transaction(function () use (
            $order,
            $status,
            $eventType,
            $actorType,
            $actorId,
            $attributes,
            $metadata,
            $approvedPreExpiryPaymentRecovery,
            $approvedFundedCancellation,
        ): PayCodeIssuanceFundingOrder {
            $locked = PayCodeIssuanceFundingOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($status === PayCodeIssuanceFundingOrderStatus::Cancelled && $locked->funded_at !== null) {
                $allocation = TreasuryAllocation::query()->where('allocation_reference', $locked->treasury_hold_reference)->first();
                $released = $allocation !== null && $allocation->status === 'released'
                    && $allocation->balance_minor === 0
                    && TreasuryAllocationOperation::query()
                        ->where('allocation_id', $allocation->getKey())
                        ->where('operation_reference', 'issuance-hold-cancel-release:'.$locked->reference)
                        ->where('operation_type', 'release')
                        ->where('status', 'committed')
                        ->where('amount_minor', $locked->required_amount_minor)
                        ->exists();
                if (! $approvedFundedCancellation || $eventType !== 'funded_order_cancelled_to_client_funds' || ! $released) {
                    throw new RuntimeException('A funded issuance order requires governed recovery or refund.');
                }
            }

            $allowed = $this->allowed($locked->status);

            if ($approvedPreExpiryPaymentRecovery
                && $locked->status === PayCodeIssuanceFundingOrderStatus::Expired
                && $status === PayCodeIssuanceFundingOrderStatus::Verifying
                && $eventType === 'reconciliation_paid_order_recovery_approved') {
                $allowed[] = PayCodeIssuanceFundingOrderStatus::Verifying;
            }

            if (! in_array($status, $allowed, true)) {
                throw new RuntimeException(
                    "Issuance Funding Order cannot move from [{$locked->status->value}] to [{$status->value}].",
                );
            }

            $from = $locked->status;
            $locked->forceFill([
                ...$attributes,
                'status' => $status,
                'version' => $locked->version + 1,
            ])->saveQuietly();
            $locked->events()->create([
                'sequence' => $locked->version,
                'event_type' => $eventType,
                'from_status' => $from,
                'to_status' => $status,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load(['fundingIntent', 'voucher', 'events']);
        }, attempts: 5);
    }

    /**
     * @return list<PayCodeIssuanceFundingOrderStatus>
     */
    private function allowed(PayCodeIssuanceFundingOrderStatus $status): array
    {
        return match ($status) {
            PayCodeIssuanceFundingOrderStatus::AwaitingPayment => [
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Verifying,
                PayCodeIssuanceFundingOrderStatus::Funded,
                PayCodeIssuanceFundingOrderStatus::Cancelled,
                PayCodeIssuanceFundingOrderStatus::Expired,
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
            ],
            PayCodeIssuanceFundingOrderStatus::PayerAcknowledged => [
                PayCodeIssuanceFundingOrderStatus::Verifying,
                PayCodeIssuanceFundingOrderStatus::Funded,
                PayCodeIssuanceFundingOrderStatus::Cancelled,
                PayCodeIssuanceFundingOrderStatus::Expired,
            ],
            PayCodeIssuanceFundingOrderStatus::Verifying => [
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Funded,
                PayCodeIssuanceFundingOrderStatus::Underfunded,
                PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
            ],
            PayCodeIssuanceFundingOrderStatus::Funded => [
                PayCodeIssuanceFundingOrderStatus::Issuing,
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
            ],
            PayCodeIssuanceFundingOrderStatus::Issuing => [
                PayCodeIssuanceFundingOrderStatus::Issued,
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
            ],
            PayCodeIssuanceFundingOrderStatus::Underfunded,
            PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous => [
                PayCodeIssuanceFundingOrderStatus::Verifying,
                PayCodeIssuanceFundingOrderStatus::Cancelled,
                PayCodeIssuanceFundingOrderStatus::Expired,
            ],
            PayCodeIssuanceFundingOrderStatus::IssuanceAttention => [
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
                PayCodeIssuanceFundingOrderStatus::Issuing,
                PayCodeIssuanceFundingOrderStatus::Cancelled,
                PayCodeIssuanceFundingOrderStatus::Expired,
            ],
            PayCodeIssuanceFundingOrderStatus::Issued,
            PayCodeIssuanceFundingOrderStatus::Cancelled,
            PayCodeIssuanceFundingOrderStatus::Expired => [],
        };
    }
}
