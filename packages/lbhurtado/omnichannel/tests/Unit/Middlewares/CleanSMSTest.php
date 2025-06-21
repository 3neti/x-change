<?php

use LBHurtado\OmniChannel\Middlewares\CleanSMS;

it('collapses multiple spaces and trims the message', function () {
    $mw = new CleanSMS();

    // Prepare a $next callback that just returns the cleaned message
    $next = function (string $cleaned, string $from, string $to) {
        return $cleaned;
    };

    $raw = "   HELLO      WORLD   THIS   IS   SMS   ";
    $result = $mw->handle($raw, '09171234567', '22560537', $next);

    expect($result)->toBe('HELLO WORLD THIS IS SMS');
});

it('leaves already-clean messages untouched', function () {
    $mw = new CleanSMS();
    $next = fn($msg, $from, $to) => $msg;

    $raw = "NO_EXTRA_SPACES";
    $result = $mw->handle($raw, 'A', 'B', $next);

    expect($result)->toBe('NO_EXTRA_SPACES');
});
