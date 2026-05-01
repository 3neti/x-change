<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Payment;

use LBHurtado\XChange\Actions\Payment\HandleVoucherPaymentWebhook;
use LBHurtado\XChange\Lifecycle\Http\Requests\Payment\HandleVoucherPaymentWebhookRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;

class HandleVoucherPaymentWebhookController
{
    public function __invoke(
        string $provider,
        HandleVoucherPaymentWebhookRequest $request,
        HandleVoucherPaymentWebhook $handle,
        ApiResponseFactory $responses,
    ) {
        $result = $handle->handle($provider, $request->payload());

        return $responses->success($result->toArray());
    }
}
