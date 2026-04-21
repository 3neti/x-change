<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Onboarding\OnboardIssuer;
use LBHurtado\XChange\Lifecycle\Http\Requests\Issuers\CreateIssuerRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Issuers\IssuerResource;

class CreateIssuerController extends Controller
{
    public function __invoke(
        CreateIssuerRequest $request,
        OnboardIssuer $action,
    ): JsonResponse {
        $result = $action->handle($request->validated());

        return IssuerResource::make($result)
            ->response()
            ->setStatusCode(201);
    }
}
