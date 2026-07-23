<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\ApiResponseFactory;

class CreateWalletTopUpController extends Controller
{
    public function __invoke(
        string $wallet,
        ApiResponseFactory $responses,
    ): JsonResponse {
        return $responses->error(
            message: 'Direct wallet top-ups are disabled. Create a Funding Intent and wait for authoritative provider settlement.',
            code: 'DIRECT_TOP_UP_DISABLED',
            errors: [
                'wallet' => $wallet,
                'replacement' => '/api/x/v1/funding-intents',
            ],
            status: 410,
        );
    }
}
