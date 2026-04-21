<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Controllers\Onboarding\OpenIssuerWalletController as LegacyOpenIssuerWalletController;
use LBHurtado\XChange\Lifecycle\Http\Requests\Issuers\CreateIssuerWalletRequest;

/**
 * Lifecycle API wrapper around the existing issuer wallet opening controller.
 *
 * This preserves current behavior while exposing the new public lifecycle route surface.
 */
class CreateIssuerWalletController extends Controller
{
    public function __invoke(CreateIssuerWalletRequest $request): JsonResponse
    {
        /** @var LegacyOpenIssuerWalletController $controller */
        $controller = app(LegacyOpenIssuerWalletController::class);

        return $controller($request);
    }
}
