<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class RequestOnDemandPayCodeIssuanceRetry
{
    public function __construct(private TransitionPayCodeIssuanceFundingOrder $transition) {}

    public const int MaximumRequests = 2;

    public const int CooldownSeconds = 60;

    /** @return array{eligible: bool, pending: bool, attempts_remaining: int, next_available_at: ?string} */
    public function availability(PayCodeIssuanceFundingOrder $order): array
    {
        $count = (int) data_get($order->metadata, 'issuance_retry.count', 0);
        $pending = data_get($order->metadata, 'issuance_retry.pending') === true;
        $requestedAt = data_get($order->metadata, 'issuance_retry.requested_at');
        $next = is_string($requestedAt)
            ? Carbon::parse($requestedAt)->addSeconds(self::CooldownSeconds)
            : null;
        $ready = $next === null || $next->isPast();
        $safe = $order->status === PayCodeIssuanceFundingOrderStatus::IssuanceAttention
            && $order->funded_at !== null
            && $order->treasury_hold_reference !== null
            && TreasuryAllocation::query()
                ->where('allocation_reference', $order->treasury_hold_reference)
                ->where('currency', $order->currency)
                ->where('status', 'active')
                ->where('balance_minor', '>=', $order->required_amount_minor)
                ->exists()
            && $order->voucher_id === null
            && $order->fundingIntent?->status === FundingIntentStatus::Settled
            && $order->fundingIntent?->settlement !== null
            && data_get($order->metadata, 'provider_reversal') === null;
        $safe = $safe && ! Checkout::query()
            ->where('funding_order_id', $order->getKey())
            ->whereHas('refundCase')->exists();

        return [
            'eligible' => $safe
                && ! $pending
                && $count < self::MaximumRequests
                && $ready,
            'pending' => $pending,
            'attempts_remaining' => max(0, self::MaximumRequests - $count),
            'next_available_at' => $safe && $count < self::MaximumRequests
                ? $next?->toIso8601String()
                : null,
        ];
    }

    public function handle(
        PayCodeIssuanceFundingOrder $order,
        string $actorType,
        string $actorId,
    ): PayCodeIssuanceFundingOrder {
        return DB::transaction(function () use ($order, $actorType, $actorId): PayCodeIssuanceFundingOrder {
            $locked = PayCodeIssuanceFundingOrder::query()
                ->with('fundingIntent.settlement')
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if (! $this->availability($locked)['eligible']) {
                throw new ConflictHttpException('Issuance retry is not available for this order. Refresh its status.');
            }

            $retry = (array) data_get($locked->metadata, 'issuance_retry', []);
            $retry['count'] = (int) ($retry['count'] ?? 0) + 1;
            $retry['requested_at'] = now()->toIso8601String();
            $retry['pending'] = true;
            $metadata = (array) $locked->metadata;
            $metadata['issuance_retry'] = $retry;

            $updated = $this->transition->handle(
                order: $locked,
                status: PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
                eventType: 'manual_issuance_retry_requested',
                actorType: $actorType,
                actorId: $actorId,
                attributes: ['metadata' => $metadata],
                metadata: ['request_number' => $retry['count']],
            );

            DB::afterCommit(static function () use ($order): void {
                ResumeOnDemandPayCodeIssuanceJob::dispatch($order->getKey());
            });

            return $updated;
        }, attempts: 5);
    }
}
