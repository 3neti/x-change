<?php

use LBHurtado\OmniChannel\Handlers\AutoReplies\HelpAutoReply;
use LBHurtado\OmniChannel\Handlers\AutoReplies\PingAutoReply;
use LBHurtado\OmniChannel\Middlewares\AutoReplySMS;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;

uses(RefreshDatabase::class);

it('short-circuits and returns a JSON help reply on HELP', function () {
    $mw      = new AutoReplySMS();
    $message = 'HELP I need assistance';
    $from    = '09171234567';
    $to      = '22560537';
    $nextCalled = false;

    // our “next” would never get called
    $next = function ($msg, $f, $t) use (&$nextCalled) {
        $nextCalled = true;
        return 'should not reach here';
    };

    $response = $mw->handle($message, $from, $to, $next);

    // ensure we never fell through
    expect($nextCalled)->toBeFalse();

    // make sure it's a JsonResponse with the handler's reply
    expect($response)->toBeInstanceOf(JsonResponse::class);
    $data = $response->getData(true);

    // compare to what HelpAutoReply would actually send
    $expected = (new HelpAutoReply())->reply($from, $to, $message);
    expect($data)->toHaveKey('message', $expected);
});


it('short-circuits and returns a JSON ping reply on PING', function () {
    $mw      = new AutoReplySMS();
    $message = 'ping are you alive?';
    $from    = '09171234567';
    $to      = '22560537';
    $nextCalled = false;

    $next = function ($msg, $f, $t) use (&$nextCalled) {
        $nextCalled = true;
        return 'should not reach here';
    };

    $response = $mw->handle($message, $from, $to, $next);

    expect($nextCalled)->toBeFalse();
    expect($response)->toBeInstanceOf(JsonResponse::class);

    $data     = $response->getData(true);
    $expected = (new PingAutoReply())->reply($from, $to, $message);
    expect($data)->toHaveKey('message', $expected);
});


it('passes through to the next layer when no keyword matches', function () {
    $mw      = new AutoReplySMS();
    $message = 'good morning';
    $from    = '09171234567';
    $to      = '22560537';

    // our “next” returns a sentinel value
    $next = fn($msg, $f, $t) => 'PASSED_THROUGH';

    $result = $mw->handle($message, $from, $to, $next);

    // should be exactly what our next() returned
    expect($result)->toBe('PASSED_THROUGH');
});

// 1) Define a stub handler that always returns null:
eval(<<<'PHP'
namespace Tests\Stubs;
use LBHurtado\OmniChannel\Contracts\AutoReplyInterface;

class NullAutoReply implements AutoReplyInterface
{
    public function reply(string $from, string $to, string $message): ?string
    {
        return null;
    }
}
PHP
);

it('falls through when the auto-reply handler returns null', function () {
    // Override the config to use our stub for the keyword "NULLTEST"
    config()->set('omnichannel.handlers.auto_replies', [
        'NULLTEST' => \Tests\Stubs\NullAutoReply::class,
    ]);

    $mw      = new AutoReplySMS();
    $message = 'NULLTEST anything goes';
    $from    = '09171234567';
    $to      = '22560537';
    $nextCalled = false;

    $next = function ($msg, $f, $t) use (&$nextCalled) {
        $nextCalled = true;
        return 'CONTINUED';
    };

    $result = $mw->handle($message, $from, $to, $next);

    // Since our handler returned null, middleware must invoke next()
    expect($nextCalled)->toBeTrue();
    expect($result)->toBe('CONTINUED');
});
