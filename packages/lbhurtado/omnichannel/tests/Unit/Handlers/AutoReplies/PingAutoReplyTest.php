<?php

use LBHurtado\OmniChannel\Handlers\AutoReplies\PingAutoReply;
use LBHurtado\OmniChannel\Contracts\AutoReplyInterface;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Freeze “now” so the timestamp portion is predictable
    Carbon::setTestNow('2024-08-02 03:17:56');
});

afterEach(function () {
    Carbon::setTestNow(); // clear
});

it('implements the AutoReplyInterface', function () {
    $handler = new PingAutoReply();
    expect($handler)->toBeInstanceOf(AutoReplyInterface::class);
});

it('returns a well-formed PONG response', function () {
    $handler = new PingAutoReply();

    $from    = '09171234567';
    $to      = '22560537';
    $message = 'PING';

    $reply = $handler->reply($from, $to, $message);

    // The shape should be:
    // PONG! Uptime: <something> Memory Usage: <something> Load Average: <something> Timestamp: 2024-08-02 03:17:56
    $pattern = '/^PONG! '
        . 'Uptime: \S+(?: .+?)? '      // at least one non-space token, maybe more
        . 'Memory Usage: \d+(\.\d+)? MB '
        . 'Load Average: \d+(\.\d+)?, \d+(\.\d+)?, \d+(\.\d+)'
        . ' Timestamp: 2024-08-02 03:17:56$/';

    expect($reply)->toMatch($pattern);
});
