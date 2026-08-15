<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Http\Requests\PartnerApi\CancelPartnerPayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeReadModel;
use LogicException;

class CancelPartnerPayCodeController extends Controller
{
    public function __invoke(
        string $code,
        CancelPartnerPayCodeRequest $request,
        PartnerApiRequestContext $context,
        PartnerPayCodeReadModel $payCodes,
        VoucherLifecycleServiceContract $lifecycle,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $issuer = $context->issuer();
        $payCodes->find($code, $issuer);

        if (! $issuer instanceof Authenticatable) {
            throw new LogicException('Partner API issuer must be an authenticatable Account owner.');
        }

        $defaultGuard = Auth::getDefaultDriver();
        Auth::shouldUse('web');
        $guard = Auth::guard('web');
        $previous = $guard->user();
        $guard->setUser($issuer);

        try {
            $result = (array) $lifecycle->cancel($code, $request->validated());
        } finally {
            if ($previous instanceof Authenticatable) {
                $guard->setUser($previous);
            } else {
                $guard->logout();
            }

            Auth::shouldUse($defaultGuard);
        }

        return $responses->success([
            'schema' => 'x-change.partner-pay-code-cancellation.v1',
            'code' => (string) data_get($result, 'code'),
            'status' => (string) data_get($result, 'status'),
            'cancelled' => (bool) data_get($result, 'cancelled'),
            'reason' => data_get($result, 'reason'),
            'treasury_release' => [
                'released' => (bool) data_get($result, 'treasury_release.released', false),
                'replayed' => (bool) data_get($result, 'treasury_release.replayed', false),
                'amount_minor' => data_get($result, 'treasury_release.amount_minor'),
                'currency' => data_get($result, 'treasury_release.currency'),
            ],
        ]);
    }
}
