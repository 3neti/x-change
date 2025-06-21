<?php

namespace LBHurtado\OmniChannel\Http\Controllers;

use LBHurtado\OmniChannel\Services\SMSRouterService;
use LBHurtado\OmniChannel\Events\SMSArrived;
use LBHurtado\OmniChannel\Data\SMSData;
use Illuminate\Http\Request;

class SMSController
{
    public function __construct(protected SMSRouterService $router) {}

    public function __invoke(Request $request)
    {
        // Transform the validated request data into an SMSData object
        $data = SMSData::from($request->all());

        // Dispatch the SMSArrived event with the parsed data
        SMSArrived::dispatch($data);

        $message = trim($data->message);

        return $this->router->handle($message, $data->from, $data->to);
    }
}
