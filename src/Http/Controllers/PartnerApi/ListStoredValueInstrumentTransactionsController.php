<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\PartnerApi\ListStoredValueInstrumentTransactionsRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerStoredValueInstrumentService;

final class ListStoredValueInstrumentTransactionsController extends Controller
{
    public function __invoke(
        ListStoredValueInstrumentTransactionsRequest $request,
        string $instrument,
        PartnerStoredValueInstrumentService $instruments,
        ApiResponseFactory $responses,
    ): JsonResponse {
        return $responses->success($instruments->transactions(
            $instrument,
            (int) ($request->validated('limit') ?? 50),
        ));
    }
}
