<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Onboarding;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Http\Requests\Onboarding\OpenIssuerWalletRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use Throwable;

class OpenIssuerWalletController extends Controller
{
    public function __invoke(
        OpenIssuerWalletRequest $request,
        OpenIssuerWallet $action,
        ApiResponseFactory $responses,
        AuditLoggerContract $audit,
    ) {
        $payload = $request->validated();

        $audit->log('issuer.wallet.open.requested', [
            'issuer_id' => data_get($payload, 'issuer_id'),
            'wallet_slug' => data_get($payload, 'wallet.slug'),
            'wallet_name' => data_get($payload, 'wallet.name'),
        ]);

        try {
            $result = $action->handle($payload);

            $audit->log('issuer.wallet.open.succeeded', [
                'issuer_id' => data_get($result, 'issuer.id'),
                'wallet_id' => data_get($result, 'wallet.id'),
                'wallet_slug' => data_get($result, 'wallet.slug'),
            ]);

            return $responses->success($result, [], 201);
        } catch (Throwable $e) {
            $audit->log('issuer.wallet.open.failed', [
                'issuer_id' => data_get($payload, 'issuer_id'),
                'wallet_slug' => data_get($payload, 'wallet.slug'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
