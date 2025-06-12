<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Brick\Money\Money;

class DepositNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Money $amount,
        public array $meta
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'engage_spark'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = $this->amount->getCurrency()->getCurrencyCode();
        $value    = $this->amount->getAmount()->toFloat();
        $alias    = $this->meta['alias'] ?? '';
        $channel  = $this->meta['channel'] ?? 'Unknown';

        return (new MailMessage)
            ->subject('Deposit Confirmed')
            ->greeting('Hello ' . optional($notifiable)->name . ',')
            ->line("Your deposit of **{$currency} {$value}** (alias: {$alias}) has been successfully confirmed.")
            ->line("Payment method: {$channel}")
            ->line("Reference: {$this->meta['referenceNumber']}")
            ->line('Thank you for using our service!');
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $currency = $this->amount->getCurrency()->getCurrencyCode();
        $value    = $this->amount->getAmount()->toFloat();
        $alias    = $this->meta['alias'] ?? '';
        $channel  = $this->meta['channel'] ?? 'Unknown';

        $message = "Deposit of {$currency} {$value} confirmed (alias: {$alias}, via {$channel}).";

        return (new EngageSparkMessage())
            ->content($message);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'amount'    => $this->amount->getAmount()->toFloat(),
            'currency'  => $this->amount->getCurrency()->getCurrencyCode(),
            'alias'     => $this->meta['alias'] ?? null,
            'channel'   => $this->meta['channel'] ?? null,
            'reference' => $this->meta['referenceNumber'] ?? null,
            'meta'      => $this->meta,
        ];
    }
}
