<?php

namespace LBHurtado\OmniChannel\Middlewares;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Closure;

class RateLimitSMS implements SMSMiddlewareInterface
{
    protected int $maxAttempts = 5; // Allow max 5 messages per time window
    protected int $decayMinutes = 1; // Reset every minute

    public function handle(string $message, string $from, string $to, Closure $next)
    {
        $cacheKey = "sms_rate_limit:{$from}";
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= $this->maxAttempts) {
            Log::warning("⛔ SMS Rate Limit Exceeded", compact('from'));

            return response()->json([
                'error' => 'Rate limit exceeded. Try again later.',
            ], 429);
        }
        Cache::put($cacheKey, $attempts + 1, now()->addMinutes($this->decayMinutes));
        Log::info("🛠 Running RateLimitSMS Middleware", compact('message', 'from', 'to'));

        return $next($message, $from, $to);
    }
}
