<?php

namespace LBHurtado\OmniChannel\Contracts;

interface SMSHandlerInterface
{
    /**
     * Handle an SMS message.
     *
     * @param array $values Parsed values from the SMS message.
     * @param string $from Sender's phone number.
     * @param string $to Receiver's phone number.
     * @return \Illuminate\Http\JsonResponse The response to send back.
     */
    public function __invoke(array $values, string $from, string $to): \Illuminate\Http\JsonResponse;
}
