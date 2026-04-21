<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Lifecycle\Http\Requests\Issuers\CreateIssuerWalletRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Issuers\IssuerWalletResource;

class CreateIssuerWalletController extends Controller
{
    public function __invoke(
        CreateIssuerWalletRequest $request,
        OpenIssuerWallet $action,
    ): JsonResponse {
        $result = $action->handle($request->validated());

        return IssuerWalletResource::make($result)
            ->response()
            ->setStatusCode(201);
    }
}
