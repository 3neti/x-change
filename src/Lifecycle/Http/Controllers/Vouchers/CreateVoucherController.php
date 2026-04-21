<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers\CreateVoucherRequest;

class CreateVoucherController extends Controller
{
    public function __invoke(CreateVoucherRequest $request): JsonResponse
    {
        // TODO: Delegate to LBHurtado\XChange\Actions\PayCode\GeneratePayCode.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'CreateVoucherController scaffolded.'],
        ], 501);
    }
}
