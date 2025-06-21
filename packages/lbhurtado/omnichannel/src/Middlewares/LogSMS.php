<?php

namespace LBHurtado\OmniChannel\Middlewares;

use Illuminate\Support\Facades\Log;
use Closure;

class LogSMS implements SMSMiddlewareInterface
{
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        Log::info("📩 Incoming SMS", compact('message', 'from', 'to'));

        return $next($message, $from, $to);
    }
}
