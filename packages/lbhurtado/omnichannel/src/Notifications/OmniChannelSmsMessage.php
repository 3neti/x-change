<?php

namespace LBHurtado\OmniChannel\Notifications;

class OmniChannelSmsMessage
{
    public function __construct(
        public string $content,
        public ?string $from = null,
    ) {}
}
