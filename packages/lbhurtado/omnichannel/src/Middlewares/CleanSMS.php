<?php

namespace LBHurtado\OmniChannel\Middlewares;

use Illuminate\Support\Facades\Log;
use Closure;

class CleanSMS implements SMSMiddlewareInterface
{
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        // Remove extra spaces, leading/trailing spaces, and convert to uppercase
        $message = trim(preg_replace('/\s+/', ' ', $message));
        Log::info("🛠 Running CleanSMS Middleware", compact('message', 'from', 'to'));

        return $next($message, $from, $to);
    }
}
