<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Checkout;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CommercialPrincipal;

final readonly class CheckoutConsoleReadModel
{
    /** @return array<string, mixed> */
    public function forOwner(CommercialPrincipal $principal, ?string $status = null): array
    {
        $base = Checkout::query()
            ->where('source', 'public.auto-generate')
            ->where('owner_type', $principal::class)
            ->where('owner_id', (string) $principal->getKey());
        $counts = [
            'all' => (clone $base)->count(),
            'awaiting_payment' => $this->countOrderStatus($base, ['awaiting_payment', 'payer_acknowledged', 'verifying']),
            'settled' => (clone $base)->whereHas('fundingOrder.fundingIntent.settlement')->count(),
            'issued' => $this->countOrderStatus($base, ['issued']),
            'attention' => $this->countOrderStatus($base, ['issuance_attention', 'underfunded', 'payment_ambiguous']),
            'refund_open' => (clone $base)->whereHas('refundCase', static fn (Builder $query): Builder => $query->where('status', 'open'))->count(),
        ];
        $query = clone $base;

        if (is_string($status) && $status !== '' && $status !== 'all') {
            match ($status) {
                'awaiting_payment' => $query->whereHas('fundingOrder', static fn (Builder $q): Builder => $q->whereIn('status', ['awaiting_payment', 'payer_acknowledged', 'verifying'])),
                'settled' => $query->whereHas('fundingOrder.fundingIntent.settlement'),
                'issued' => $query->whereHas('fundingOrder', static fn (Builder $q): Builder => $q->where('status', 'issued')),
                'attention' => $query->whereHas('fundingOrder', static fn (Builder $q): Builder => $q->whereIn('status', ['issuance_attention', 'underfunded', 'payment_ambiguous'])),
                'refund_open' => $query->whereHas('refundCase', static fn (Builder $q): Builder => $q->where('status', 'open')),
                default => null,
            };
        }

        /** @var LengthAwarePaginator $page */
        $page = $query->with([
            'contact', 'refundCase', 'events',
            'fundingOrder.events',
            'fundingOrder.fundingIntent.settlement',
        ])->latest('id')->paginate(25);

        return [
            'counts' => $counts,
            'filter' => $status ?? 'all',
            'rows' => $page->getCollection()->map(function (Checkout $checkout): array {
                $order = $checkout->fundingOrder;
                $settlement = $order?->fundingIntent?->settlement;
                $mobile = $checkout->contact?->mobile ?? $checkout->visitor_mobile_ciphertext;

                return [
                    'reference' => $checkout->reference,
                    'order_reference' => $order?->reference,
                    'status' => $order?->status->value ?? $checkout->status,
                    'method' => $checkout->selected_method,
                    'contact' => is_string($mobile) && mb_strlen($mobile) >= 4
                        ? '••••'.substr($mobile, -4) : null,
                    'contact_source' => $checkout->contact_source,
                    'expected_minor' => $order?->expected_payment_minor,
                    'settled_minor' => $settlement?->gross_amount_minor,
                    'currency' => $order?->currency,
                    'refund_status' => $checkout->refundCase?->status,
                    'attention_reason' => $order?->events->last()?->event_type,
                    'last_activity_at' => $order?->updated_at?->toIso8601String() ?? $checkout->updated_at?->toIso8601String(),
                    'timeline' => collect($order?->events ?? [])
                        ->concat($checkout->events)
                        ->map(static fn ($event): array => [
                            'type' => $event->event_type,
                            'at' => $event->occurred_at?->toIso8601String(),
                        ])
                        ->sortBy('at')->values()->all(),
                ];
            })->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /** @param Builder<Checkout> $base @param list<string> $statuses */
    private function countOrderStatus(Builder $base, array $statuses): int
    {
        return (clone $base)->whereHas('fundingOrder', static fn (Builder $query): Builder => $query->whereIn('status', $statuses))->count();
    }
}
