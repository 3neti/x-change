<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Checkout;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;

final class LinkSettledCheckoutContactJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [5, 15, 45, 120];

    public function __construct(public readonly int $orderId, public readonly int $observationId)
    {
        $this->onQueue((string) config('x-change.issuance_funding.on_demand.queue', 'x-change-issuance'));
    }

    public function uniqueId(): string
    {
        return 'checkout-contact:'.$this->orderId;
    }

    public function handle(CheckoutLifecycle $checkouts): void
    {
        $order = PayCodeIssuanceFundingOrder::query()->findOrFail($this->orderId);
        $observation = ProviderFundingObservation::query()->findOrFail($this->observationId);

        if ($order->fundingIntent?->settlement?->provider_funding_observation_id !== $observation->getKey()) {
            return;
        }

        $checkouts->settled($order, $observation);
    }
}
