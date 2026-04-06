<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Onboarding;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Onboarding\OnboardIssuer;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Http\Requests\Onboarding\OnboardIssuerRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use Throwable;

class OnboardIssuerController extends Controller
{
    public function __invoke(
        OnboardIssuerRequest $request,
        OnboardIssuer $action,
        ApiResponseFactory $responses,
        AuditLoggerContract $audit,
    ) {
        $payload = $request->validated();

        $audit->log('issuer.onboard.requested', [
            'email' => data_get($payload, 'email'),
            'mobile' => data_get($payload, 'mobile'),
            'country' => data_get($payload, 'country'),
        ]);

        try {
            $result = $action->handle($payload);

            $audit->log('issuer.onboard.succeeded', [
                'issuer_id' => data_get($result, 'issuer.id'),
                'email' => data_get($result, 'issuer.email'),
                'mobile' => data_get($result, 'issuer.mobile'),
            ]);

            return $responses->success($result, [], 201);
        } catch (Throwable $e) {
            $audit->log('issuer.onboard.failed', [
                'email' => data_get($payload, 'email'),
                'mobile' => data_get($payload, 'mobile'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
