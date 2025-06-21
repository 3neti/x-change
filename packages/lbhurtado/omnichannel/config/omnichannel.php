<?php

return [
    'url' => env('OMNI_CHANNEL_URL', 'http://13.250.187.118:8063/core/sender'),
    'access_key' => env('OMNI_CHANNEL_ACCESS_KEY'),
    'service' => env('OMNI_CHANNEL_SERVICE', 'mt'),
    'handlers' => [
        'auto_replies' => [
            'HELP' => \LBHurtado\OmniChannel\Handlers\AutoReplies\HelpAutoReply::class,
            'PING' => \LBHurtado\OmniChannel\Handlers\AutoReplies\PingAutoReply::class,
        ]
    ],
];
