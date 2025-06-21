<?php

use LBHurtado\OmniChannel\Middlewares\SMSMiddlewareInterface;
use LBHurtado\OmniChannel\Services\SMSRouterService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\JsonResponse;

uses(WithFaker::class);

beforeEach(function () {
    $this->router = new SMSRouterService();
});

it('rejects middleware that does not implement SMSMiddlewareInterface', function () {
    $this->router->register('CMD', fn() => null, [
        class_exists(\stdClass::class) ? \stdClass::class : 'stdClass',
    ]);
})->throws(InvalidArgumentException::class);

it('returns 404 on unknown command', function () {
    $response = $this->router->handle('UNKNOWN', '123', '456');
    expect($response)
        ->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['message' => 'Unknown command. Please try again.']);
});

it('matches a simple callable handler with no params', function () {
    $this->router->register('PING', fn($v, $from, $to) => response()->json([
        'pong' => true, 'from' => $from, 'to' => $to
    ]));

    $resp = $this->router->handle('PING', 'A', 'B');
    expect($resp->getData(true))
        ->toMatchArray(['pong' => true, 'from' => 'A', 'to' => 'B'])
        ->and($resp->getStatusCode())->toBe(200);
});

it('injects URL‐style values into handler', function () {
    $this->router->register('SEND {mobile} {amount}', function ($vals) {
        return response()->json($vals);
    });

    $resp = $this->router->handle('SEND 09171234567 500', '', '');
    expect($resp->getData(true))
        ->toMatchArray(['mobile' => '09171234567', 'amount' => '500']);
});

it('invokes a handler class with __invoke method', function () {
    // Create a temporary handler stub:
    eval(<<<'PHP'
    namespace Tests\Stubs;
    class InvokableHandler {
        public function __invoke(array $vals, string $from, string $to) {
            return response()->json(['ok'=>true,'vals'=>$vals,'from'=>$from,'to'=>$to]);
        }
    }
    PHP);

    $this->router->register('HI', \Tests\Stubs\InvokableHandler::class);

    $resp = $this->router->handle('HI', 'X', 'Y');
    expect($resp->getData(true))
        ->toMatchArray(['ok'=>true,'vals'=>[],'from'=>'X','to'=>'Y']);
});

it('invokes a handler class with handle method', function () {
    eval(<<<'PHP'
    namespace Tests\Stubs;
    class HandleMethodHandler {
        public function handle(array $vals, string $from, string $to) {
            return response()->json(['handled'=>true,'from'=>$from,'to'=>$to]);
        }
    }
    PHP);

    $this->router->register('HELLO', \Tests\Stubs\HandleMethodHandler::class);

    $resp = $this->router->handle('HELLO', 'AA', 'BB');
    expect($resp->getData(true))
        ->toMatchArray(['handled'=>true,'from'=>'AA','to'=>'BB']);
});

it('runs through middleware in the right order', function () {
    // define two piece of middleware that record order
    eval(<<<'PHP'
    namespace Tests\Stubs;
    use LBHurtado\OmniChannel\Middlewares\SMSMiddlewareInterface;
    class FirstMw implements SMSMiddlewareInterface {
        public function handle($msg, $from, $to, $next) {
            $msg .= '|1';
            return $next($msg, $from, $to);
        }
    }
    class SecondMw implements SMSMiddlewareInterface {
        public function handle($msg, $from, $to, $next) {
            $msg .= '|2';
            return $next($msg, $from, $to);
        }
    }
    PHP);

    // handler echoes back the message
    $this->router->register(
        'M {text}',
        function ($vals, $from, $to) {
            return response()->json(['final'=> $vals['text']]);
        },
        [\Tests\Stubs\FirstMw::class, \Tests\Stubs\SecondMw::class]
    );

    $resp = $this->router->handle('M hello', 'F', 'T');
    // the |2 should be applied after |1
    expect($resp->getData(true)['final'])->toBe('hello|1|2');
});
