<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Str;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use LBHurtado\XChange\Models\VoucherCollection;

final class PartnerPaymentEventOutbox
{
    public function record(VoucherCollection $collection): ?PartnerPaymentEvent
    {
        if (! config('x-change.partner_api.payment_events.enabled', false) || ! $collection->isSucceeded() || $collection->collected_amount_minor <= 0) {
            return null;
        }

        $bindings = PartnerApiPayCodeReference::query()->where('voucher_id', $collection->voucher_id)->with(['client', 'voucher'])->get();
        if ($bindings->count() !== 1) {
            return null;
        }
        $binding = $bindings->first();
        $client = $binding->client;
        if (! $client || ! $client->isActive()) {
            return null;
        }

        $eventId = (string) Str::uuid();

        return PartnerPaymentEvent::query()->firstOrCreate(['collection_id' => $collection->getKey()], [
            'event_id' => $eventId,
            'partner_api_client_id' => $client->getKey(),
            'partner_reference' => $client->reference,
            'body' => json_encode([
                'type' => 'payment.collected.v1',
                'event_id' => $eventId,
                'occurred_at' => $collection->completed_at->toISOString(),
                'partner_reference' => $client->reference,
                'external_reference' => $binding->external_reference,
                'pay_code' => $binding->voucher->code,
                'collection_id' => (string) $collection->getKey(),
                'amount_minor' => (int) $collection->collected_amount_minor,
                'currency' => strtoupper($collection->currency),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }
}
