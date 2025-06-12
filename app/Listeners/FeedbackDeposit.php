<?php

namespace App\Listeners;

use LBHurtado\Wallet\Events\DepositConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\DepositNotification;
use Illuminate\Queue\InteractsWithQueue;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Brick\Money\Money;

class FeedbackDeposit implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct() {}

    public function handle(DepositConfirmed $event): void
    {
        Log::debug('[FeedbackDeposit] Received DepositConfirmed event', ['transaction_id' => $event->transaction->id]);

        $transaction = $event->transaction;

        // 1) Check transaction type
        if ($transaction->type !== Transaction::TYPE_DEPOSIT) {
            Log::debug('[FeedbackDeposit] Skipping: Not a deposit', ['type' => $transaction->type]);
            return;
        }
        Log::debug('[FeedbackDeposit] Transaction is a deposit');

        // 2) Check payable/notifiable
        $payable = $transaction->payable;

        if (! method_exists($payable, 'notify')) {
            Log::warning('[FeedbackDeposit] Payable is not Notifiable', [
                'payable_type' => get_class($payable),
                'payable_id'   => $payable->getKey(),
            ]);
            return;
        }
        Log::debug('[FeedbackDeposit] Payable is Notifiable', [
            'payable_type' => get_class($payable),
            'payable_id'   => $payable->getKey(),
        ]);

        // 3) Build amount
        $amount = Money::ofMinor(
            $transaction->amount,
            Number::defaultCurrency()
        );
        Log::debug('[FeedbackDeposit] Calculated amount for notification', [
            'amount'   => $amount->getAmount()->toFloat(),
            'currency' => $amount->getCurrency()->getCurrencyCode(),
        ]);

        // 4) Send notification
        Log::info('[FeedbackDeposit] Dispatching DepositNotification', [
            'payable_id'      => $payable->getKey(),
            'payable_type'    => get_class($payable),
            'notification'    => DepositNotification::class,
        ]);

        $payable->notify(
            new DepositNotification($amount, $transaction->meta)
        );

        Log::info('[FeedbackDeposit] DepositNotification sent successfully');
    }
}
