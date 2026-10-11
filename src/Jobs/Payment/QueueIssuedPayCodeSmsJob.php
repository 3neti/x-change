<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LBHurtado\XChange\Actions\Payment\Pipes\QueueSettledPaymentSms;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\SettledPaymentProcess;

final class QueueIssuedPayCodeSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $fundingOrderId)
    {
        $this->onQueue('x-change-feedback');
    }

    public function handle(QueueSettledPaymentSms $sms): void
    {
        $order = PayCodeIssuanceFundingOrder::query()->findOrFail($this->fundingOrderId);
        $settlement = FundingSettlement::query()
            ->where('funding_intent_id', $order->funding_intent_id)
            ->first();
        $process = $settlement === null ? null : SettledPaymentProcess::query()
            ->where('payment_type', 'funding_settlement')
            ->where('payment_id', $settlement->getKey())
            ->where('flow', 'public_auto_generate')
            ->first();

        if ($process !== null) {
            $sms->handle($process, static fn (SettledPaymentProcess $process): null => null);
        }
    }
}
