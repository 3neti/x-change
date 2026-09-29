<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Funding\ExpireOnDemandIssuanceFundingOrder;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final class ExpireOnDemandIssuanceFundingOrdersCommand extends Command
{
    protected $signature = 'xchange:funding:expire-issuance-orders
        {--limit=100 : Maximum orders to inspect in this run}';

    protected $description = 'Expire due on-demand issuance orders and release mature transfer-amount leases';

    public function handle(ExpireOnDemandIssuanceFundingOrder $expire): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $expired = 0;
        $released = 0;

        PayCodeIssuanceFundingOrder::query()
            ->whereIn('status', [
                PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Underfunded,
                PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
            ])
            ->where('expires_at', '<=', now())
            ->oldest('expires_at')
            ->limit($limit)
            ->get()
            ->each(function (PayCodeIssuanceFundingOrder $order) use ($expire, &$expired): void {
                if ($expire->handle($order)->status === PayCodeIssuanceFundingOrderStatus::Expired) {
                    $expired++;
                }
            });

        PayCodeIssuanceFundingOrder::query()
            ->with('fundingIntent')
            ->whereIn('status', [
                PayCodeIssuanceFundingOrderStatus::Cancelled,
                PayCodeIssuanceFundingOrderStatus::Expired,
            ])
            ->whereNotNull('amount_lease_active_key')
            ->where('amount_lease_reusable_after', '<=', now())
            ->oldest('amount_lease_reusable_after')
            ->limit($limit)
            ->get()
            ->each(function (PayCodeIssuanceFundingOrder $order) use (&$released): void {
                $intentStatus = $order->fundingIntent?->status;

                if ($intentStatus !== null && ! in_array($intentStatus, [
                    FundingIntentStatus::Settled,
                    FundingIntentStatus::Expired,
                    FundingIntentStatus::Cancelled,
                ], true)) {
                    return;
                }

                $order->forceFill([
                    'amount_lease_active_key' => null,
                    'amount_lease_released_at' => now(),
                ])->saveQuietly();
                $released++;
            });

        $this->components->info("Expired {$expired} order(s); released {$released} mature amount lease(s).");

        return self::SUCCESS;
    }
}
