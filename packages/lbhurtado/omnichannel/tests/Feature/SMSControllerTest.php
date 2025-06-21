<?php

use LBHurtado\OmniChannel\Http\Controllers\SMSController;
use LBHurtado\OmniChannel\Services\SMSRouterService;
use LBHurtado\OmniChannel\Events\SMSArrived;
use LBHurtado\OmniChannel\Data\SMSData;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\Request;

it('fires SMSArrived and delegates to router->handle()', function () {
    // 1) Fake only the SMSArrived event
    Event::fake([SMSArrived::class]);

    // 2) Prepare a dummy response from the router
    $dummyResponse = response()->json(['ok' => true], 200);

    // 3) Bind a mock SMSRouterService into the container
    $mockRouter = Mockery::mock(SMSRouterService::class);
    $mockRouter
        ->shouldReceive('handle')
        ->once()
        ->with('HELLO', '09171234567', '22560537')
        ->andReturn($dummyResponse);
    $this->app->instance(SMSRouterService::class, $mockRouter);

    // 4) Mock a plain HTTP Request so all() returns our payload
    $payload = [
        'from'    => '09171234567',
        'to'      => '22560537',
        'message' => 'HELLO',
    ];
    $mockRequest = Mockery::mock(Request::class);
    $mockRequest
        ->shouldReceive('all')
        ->once()
        ->andReturn($payload);

    // 5) Resolve & invoke the controller
    $controller = app(SMSController::class);
    $response   = $controller($mockRequest);

    // 6) Assert the controller returned exactly what the router returned
    expect($response)->toBe($dummyResponse);

    // 7) Assert the SMSArrived event was dispatched with the right data
    Event::assertDispatched(SMSArrived::class, function (SMSArrived $event) use ($payload) {
        return $event->data instanceof SMSData
            && $event->data->from    === $payload['from']
            && $event->data->to      === $payload['to']
            && $event->data->message === $payload['message'];
    });
});
