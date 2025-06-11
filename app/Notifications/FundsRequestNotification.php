<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Brick\Money\Money;

class FundsRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $url,
        public Money $amount
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'engage_spark'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Add Funds to Your Wallet')
            ->greeting('Hi ' . optional($notifiable)->name . ',')
            ->line("You’ve requested to add **{$this->amount->formatTo('en_PH')}** to your wallet.")
            ->line("Please scan the QR code using your e-wallet app (e.g., GCash or Maya) to complete the payment.")
            ->action('Scan QR Code', $this->url)
            ->line('We’ll credit the amount once we confirm the payment.')
            ->line('Thank you for using our platform!');
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        return (new EngageSparkMessage())
            ->content("Add {$this->amount->getCurrency()->getCurrencyCode()} {$this->amount->getAmount()} to your wallet. Scan QR: {$this->url}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'amount' => $this->amount->getAmount()->toFloat(),
            'currency' => $this->amount->getCurrency()->getCurrencyCode(),
            'url' => $this->url,
        ];
    }
}
