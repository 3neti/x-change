<?php

namespace LBHurtado\OmniChannel\Middlewares;

interface SMSMiddlewareInterface
{
    /**
     * Handle an incoming SMS.
     *
     * @param  string  $message
     * @param  string  $from
     * @param  string  $to
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(string $message, string $from, string $to, \Closure $next);
}
