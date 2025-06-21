<?php

namespace LBHurtado\OmniChannel\Notifications;

use Illuminate\Notifications\Notification;

class AdhocNotification extends Notification
{
    public function __construct(protected $message) {}
    public function via($notifiable): array
    {
        return ['omnichannel'];
    }

    public function toOmnichannel($notifiable): OmniChannelSmsMessage
    {
        return (new OmniChannelSmsMessage($this->message));
    }
}
