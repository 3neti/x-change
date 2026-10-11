<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Checkout;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;

final class BackfillPublicCheckoutsCommand extends Command
{
    protected $signature = 'x-change:checkout:backfill-public {--dry-run : Count eligible orders without writing}';

    protected $description = 'Idempotently link existing public issuance funding orders to Checkout.';

    public function handle(CheckoutLifecycle $checkouts): int
    {
        $query = PayCodeIssuanceFundingOrder::query()
            ->where('metadata->source', 'public.auto-generate');
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Eligible public orders: {$count}");

            return self::SUCCESS;
        }

        $processed = 0;
        $query->with('fundingIntent.settlement.providerFundingObservation')
            ->chunkById(100, function ($orders) use ($checkouts, &$processed): void {
                foreach ($orders as $order) {
                    $checkout = $checkouts->place($order);
                    $observation = $order->fundingIntent?->settlement?->providerFundingObservation;

                    if ($observation !== null && $checkout->contact_source === null) {
                        $checkouts->settled($order, $observation);
                    }

                    $status = match ($order->status->value) {
                        'issued' => 'issued',
                        'cancelled' => 'cancelled',
                        'expired' => 'expired',
                        default => $observation !== null ? 'settled' : 'placed',
                    };
                    if ($checkout->status !== $status) {
                        $checkout->forceFill(['status' => $status])->save();
                    }
                    $processed++;
                }
            });

        $this->info("Public checkouts inspected: {$processed}");

        return self::SUCCESS;
    }
}
