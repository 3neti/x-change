<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LBHurtado\EngageSpark\EngageSparkMessage;

class SendOTP extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $mobile, protected string $otp){}

    public function via(object $notifiable): array
    {
        $notifiable->route('engage_spark', $this->mobile);

        return ['engage_spark'];
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $message = __(":otp is your authentication code. Do not share.\n- :app", [
            'otp' => $this->otp,
            'app' => config('app.name')
        ]);

        return (new EngageSparkMessage())
            ->content($message);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'notifiable' => $notifiable->name,
            'input' => $this->otp,
        ];
    }
}
