<?php

namespace LBHurtado\OmniChannel\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use LBHurtado\OmniChannel\Data\SMSData;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\Channel;

class SMSArrived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SMSData $data){}
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
