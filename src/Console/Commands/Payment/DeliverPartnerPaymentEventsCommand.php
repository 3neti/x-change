<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use LBHurtado\XChange\Services\Payment\PartnerPaymentEventDelivery;

final class DeliverPartnerPaymentEventsCommand extends Command
{
    protected $signature = 'x-change:partner-payment-events:deliver {--limit=20} {--retry= : Explicitly requeue one failed event UUID; does not repeat a payment}';

    protected $description = 'Deliver durable partner collection notifications without executing payments.';

    public function handle(PartnerPaymentEventDelivery $delivery): int
    {
        if (! config('x-change.partner_api.payment_events.enabled', false)) {
            $this->line('Partner payment events are disabled.');

            return self::SUCCESS;
        }
        if ($id = $this->option('retry')) {
            PartnerPaymentEvent::query()->where('event_id', $id)->where('status', 'failed')
                ->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'lease_token' => null, 'lease_expires_at' => null]);
        }
        $events = PartnerPaymentEvent::query()->whereIn('status', ['pending', 'sending'])->where('available_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now()))
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->pluck('id');
        $delivered = 0;
        foreach ($events as $id) {
            $delivered += (int) $delivery->deliver((int) $id);
        }
        $this->line('Considered '.$events->count().'; delivered '.$delivered.'.');

        return self::SUCCESS;
    }
}
