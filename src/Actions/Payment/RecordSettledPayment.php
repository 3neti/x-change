<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use LBHurtado\XChange\Events\PaymentSettled;
use LBHurtado\XChange\Models\SettledPaymentProcess;

final class RecordSettledPayment
{
    public function handle(string $paymentType, int $paymentId, int $observationId, string $flow, int $grossAmountMinor): SettledPaymentProcess
    {
        $process = SettledPaymentProcess::query()->firstOrCreate(
            ['payment_type' => $paymentType, 'payment_id' => $paymentId],
            [
                'observation_id' => $observationId,
                'flow' => $flow,
                'gross_amount_minor' => $grossAmountMinor,
                'sms_enabled_snapshot' => (bool) config('x-change.payment_feedback.sms.enabled', false)
                    && (bool) config("x-change.payment_feedback.sms.flows.{$flow}", false),
            ],
        );

        if ($process->wasRecentlyCreated) {
            PaymentSettled::dispatch((int) $process->getKey());
        }

        return $process;
    }
}
