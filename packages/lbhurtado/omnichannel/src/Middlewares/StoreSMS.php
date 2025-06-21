<?php

namespace LBHurtado\OmniChannel\Middlewares;

use LBHurtado\OmniChannel\Models\SMS;
use Closure;

class StoreSMS implements SMSMiddlewareInterface
{
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        SMS::create([
            'from' => $from,
            'to' => $to,
            'message' => $message,
        ]);

        return $next($message, $from, $to);
    }
}
