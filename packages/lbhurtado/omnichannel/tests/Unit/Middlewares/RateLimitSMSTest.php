<?php

use LBHurtado\OmniChannel\Middlewares\RateLimitSMS;
use Illuminate\Support\Facades\Cache;

it('allows up to maxAttempts within decay period and then blocks', function () {
    // Freeze time so our Cache TTL behaves predictably
    Cache::flush();
    $mw = new RateLimitSMS();

    $from = '09171234567';
    $to   = '22560537';
    $message = 'ANYTHING';

    // First 5 attempts should all call $next and return its result
    $next = fn($msg, $fromArg, $toArg) => response()->json(['ok' => true], 200);

    for ($i = 1; $i <= 5; $i++) {
        $response = $mw->handle($message, $from, $to, $next);
        expect($response->getStatusCode())->toBe(200);
        expect($response->getData())->toEqual((object) ['ok' => true]);
    }

    // Sixth attempt should be throttled
    $tooMany = $mw->handle($message, $from, $to, $next);
    expect($tooMany->getStatusCode())->toBe(429);
    expect($tooMany->getData())->toEqual((object) ['error' => 'Rate limit exceeded. Try again later.']);
});
