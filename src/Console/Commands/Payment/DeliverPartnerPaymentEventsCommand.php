<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Payment;

use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Payment\DeliverPartnerPaymentEvent;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use Throwable;

final class DeliverPartnerPaymentEventsCommand extends Command
{
    protected $signature = 'x-change:partner-payment-events:deliver {--limit=20} {--retry= : Explicitly requeue one failed event UUID; does not repeat a payment}';

    protected $description = 'Queue due partner collection notifications without HTTP or payment execution.';

    public function handle(): int
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
        foreach ($events as $id) {
            try {
                DeliverPartnerPaymentEvent::dispatch((int) $id);
            } catch (Throwable) {
                $this->error('Notification enqueue unavailable; durable events remain recoverable.');

                return self::FAILURE;
            }
        }
        $this->line('Considered '.$events->count().' due event(s) for unique queue dispatch.');

        return self::SUCCESS;
    }
}
