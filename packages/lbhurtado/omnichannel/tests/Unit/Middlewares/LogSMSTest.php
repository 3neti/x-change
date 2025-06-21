<?php

use LBHurtado\OmniChannel\Middlewares\LogSMS;
use Illuminate\Support\Facades\Log;

it('logs the incoming SMS then continues', function () {
    // Arrange: fake the Log facade
    Log::shouldReceive('info')
        ->once()
        ->with('📩 Incoming SMS', [
            'message' => 'TEST MESSAGE',
            'from'    => '09171234567',
            'to'      => '22560537',
        ]);

    $mw = new LogSMS();

    // Act: invoke handle()
    $result = $mw->handle(
        'TEST MESSAGE',
        '09171234567',
        '22560537',
        // next callback returns a sentinel value
        fn ($msg, $from, $to) => "NEXT CALLED: {$msg}|{$from}|{$to}"
    );

    // Assert: middleware forwarded to next and returned its result
    expect($result)->toBe('NEXT CALLED: TEST MESSAGE|09171234567|22560537');
});
