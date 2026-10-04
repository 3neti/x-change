<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StorePartnerApiClientRequest;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiIssuerResolver;
use Throwable;

final class CockpitPartnerApiClientController extends Controller
{
    public function store(
        StorePartnerApiClientRequest $request,
        CreatePartnerApiClient $create,
        WalletAccessContract $wallets,
        PartnerApiIssuerResolver $issuers,
    ): JsonResponse {
        $validated = $request->validated();
        $issuer = $issuers->resolve($validated['issuer_type'] ?? PartnerApiIssuerResolver::Account, $validated['issuer_id']);
        try {
            $wallets->resolveForUser($issuer);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'issuer_id' => ['The selected issuer does not have an active X-Change Account.'],
            ]);
        }

        $credential = $create->handle(
            name: $validated['name'],
            issuer: $issuer,
            environment: 'sandbox',
            scopes: $validated['scopes'],
            mandate: [
                'currencies' => $validated['currencies'],
                'settlement_rails' => $validated['settlement_rails'],
                'unbound_pay_codes' => $validated['unbound_pay_codes'],
                'maximum_amount_minor' => $validated['maximum_amount_minor'],
                'daily_principal_limit_minor' => $validated['daily_principal_limit_minor'],
                'voucher_profiles' => $validated['voucher_profiles'] ?? ['disbursement'],
                'stored_value_spend' => $validated['stored_value_spend'] ?? ['enabled' => false],
            ],
            operator: $request->user(),
        );

        return response()->json([
            'schema' => 'x-change.partner-api-credential.v1',
            'reference' => $credential->reference,
            'client_id' => $credential->client_id,
            'client_secret' => $credential->client_secret,
            'environment' => $credential->environment,
            'scopes' => $credential->scopes,
            'mandate' => $credential->mandate,
            'secret_display' => 'one_time_only',
        ], 201, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
