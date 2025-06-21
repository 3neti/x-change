<?php

use LBHurtado\OmniChannel\Middlewares\{AutoReplySMS, CleanSMS, LogSMS, RateLimitSMS, StoreSMS};
use LBHurtado\OmniChannel\Handlers\{SMSAutoRegister, SMSRegister};
use LBHurtado\OmniChannel\Services\SMSRouterService;
use Illuminate\Support\Facades\Log;

Log::info('📌 SMS Routes Loaded');

/** @var SMSRouterService $router */
$router = resolve(SMSRouterService::class);
//Log::info("✅  Resolved SMSRouterService instance.", ['instance' => get_class($router)]);

$router->register('REGISTER {mobile?} {extra?}', SMSRegister::class);
$router->register('REG {email} {extra?}', SMSAutoRegister::class);

$router->register(
    '{message}',
    function ($values, $from, $to) {
        Log::info("📩 SMS Route Matched", ['message' => $values['message'], 'from' => $from, 'to' => $to]);

        return response()->json([
            'message' => null
        ]);
    },
    [
        LogSMS::class,        // 📥 raw audit
        RateLimitSMS::class,  // ⛔ spam guard
        CleanSMS::class,      // 🧹 normalize
        AutoReplySMS::class,  // 🤖 brain
        StoreSMS::class,      // 💾 persist final
        LogSMS::class,        // 📋 post-save log
    ]
);
