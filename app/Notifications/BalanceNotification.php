<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use LBHurtado\Wallet\Enums\WalletType;
use Illuminate\Bus\Queueable;
use Brick\Money\Money;

class BalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Money $balance,
        public ?WalletType $walletType = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'engage_spark'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->walletType?->value ?? 'default';
        return (new MailMessage)
            ->subject("Your {$type} wallet balance")
            ->line("Your current balance is {$this->balance->getCurrency()->getCurrencyCode()} {$this->balance->getAmount()}.")
            ->line('Thank you for using our service!');
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $type = $this->walletType?->value ?? 'default';
        return (new EngageSparkMessage())
            ->content("Your {$type} wallet balance is {$this->balance->getCurrency()->getCurrencyCode()} {$this->balance->getAmount()}.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'balance' => (string) $this->balance->getAmount(),
            'currency' => $this->balance->getCurrency()->getCurrencyCode(),
            'wallet_type' => $this->walletType?->value ?? 'default',
        ];
    }
}
