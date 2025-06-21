<?php

namespace LBHurtado\OmniChannel\Notifications;

use LBHurtado\OmniChannel\Services\OmniChannelService;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Notifications\Notification;

class OmniChannelSmsChannel
{
    public function __construct(protected OmniChannelService $router) {}

    /**
     * Send the given notification.
     */

    public function send($notifiable, Notification $notification): void
    {
        // 1) Get the recipient(s)
        $to = $notifiable->routeNotificationFor('omnichannel', $notification);
        if (! $to) {
            return;
        }

        // 2) Ask the notification for its message payload
        $payload = $notification->toOmnichannel($notifiable);
        // If the notification returned a simple string, wrap it:
        if (is_string($payload)) {
            $payload = new \LBHurtado\OmniChannel\Notifications\OmniChannelSmsMessage($payload);
        }

        // 3) Normalize the phone number
        $phone = new PhoneNumber($to, config('omnichannel.default_country', 'PH'));
        $recipient = preg_replace('/\D+/', '', $phone->formatE164());

        // 4) Determine “from”
        $from = $payload->from
            ?? config('omnichannel.default_sender_id');

        // 5) Send it
        $this->router->send($recipient, $payload->content, $from);
    }
}
