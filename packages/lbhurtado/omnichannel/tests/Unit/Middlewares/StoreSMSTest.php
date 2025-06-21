<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\OmniChannel\Middlewares\StoreSMS;
use LBHurtado\OmniChannel\Models\SMS;

uses(RefreshDatabase::class);

it('persists the SMS record then continues', function () {
    // Arrange
    $mw      = new StoreSMS();
    $message = 'HELLO STORE';
    $from    = '09171234567';
    $to      = '22560537';

    // Act: run the middleware and capture the return from the "next" layer
    $returned = $mw->handle(
        $message,
        $from,
        $to,
        fn ($msg, $f, $t) => "NEXT_CALLED:{$msg}|{$f}|{$t}"
    );

    // Assert: middleware forwarded to next with correct arguments
    expect($returned)->toBe("NEXT_CALLED:{$message}|{$from}|{$to}");

    // Assert: SMS was persisted with the correct attributes
    $this->assertDatabaseHas('sms', [
        'message' => $message,
        'from'    => $from,
        'to'      => $to,
    ]);
});
