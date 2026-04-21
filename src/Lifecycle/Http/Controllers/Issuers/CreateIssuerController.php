<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Issuers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Controllers\Onboarding\OnboardIssuerController as LegacyOnboardIssuerController;
use LBHurtado\XChange\Lifecycle\Http\Requests\Issuers\CreateIssuerRequest;

/**
 * Lifecycle API wrapper around the existing onboarding controller.
 *
 * This preserves current behavior while exposing the new public lifecycle route surface.
 */
class CreateIssuerController extends Controller
{
    public function __invoke(CreateIssuerRequest $request): JsonResponse
    {
        /** @var LegacyOnboardIssuerController $controller */
        $controller = app(LegacyOnboardIssuerController::class);

        return $controller($request);
    }
}
