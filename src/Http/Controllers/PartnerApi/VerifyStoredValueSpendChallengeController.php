<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\PartnerApi\VerifyStoredValueSpendChallengeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerStoredValueSpendChallengeService;

final class VerifyStoredValueSpendChallengeController extends Controller
{
    public function __invoke(
        VerifyStoredValueSpendChallengeRequest $request,
        string $instrument,
        string $challenge,
        PartnerStoredValueSpendChallengeService $challenges,
        ApiResponseFactory $responses,
    ): JsonResponse {
        return $responses->success($challenges->verify(
            instrument: $instrument,
            challengeReference: $challenge,
            code: (string) $request->validated('code'),
        ));
    }
}
