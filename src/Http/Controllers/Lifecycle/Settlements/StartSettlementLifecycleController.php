<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle\Settlements;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Services\ApiResponseFactory;

class StartSettlementLifecycleController extends Controller
{
    public function __invoke(
        string $code,
        SettlementFlowPreparationContract $prepare,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            throw (new ModelNotFoundException)->setModel(Voucher::class, [$code]);
        }

        $result = $prepare->prepare($voucher);

        return $responses->success($result);
    }
}
