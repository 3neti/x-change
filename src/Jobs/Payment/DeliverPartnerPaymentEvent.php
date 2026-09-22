<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use LBHurtado\XChange\Services\Payment\PartnerPaymentEventDelivery;
use RuntimeException;
use Throwable;

final class DeliverPartnerPaymentEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $eventId)
    {
        $connection = (string) config('x-change.partner_api.payment_events.connection', 'database');
        $store = (string) config('x-change.partner_api.payment_events.lock_store', 'database');
        if (! in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)
            || ! in_array(config('cache.stores.'.$store.'.driver'), ['database', 'redis', 'memcached', 'dynamodb'], true)) {
            throw new RuntimeException('Partner payment events require a durable queue and shared lock store.');
        }
        $this->onConnection($connection);
        $this->onQueue((string) config('x-change.partner_api.payment_events.queue', 'partner-payments'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'partner-payment-event:'.$this->eventId;
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('x-change.partner_api.payment_events.lock_store', 'database'));
    }

    public function handle(PartnerPaymentEventDelivery $delivery): void
    {
        $delivery->deliver($this->eventId);
    }

    public function failed(?Throwable $exception): void
    {
        PartnerPaymentEvent::query()->whereKey($this->eventId)->whereIn('status', ['pending', 'sending'])
            ->update(['last_error' => 'notification_worker_failed']);
    }
}
