<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\PartnerApi\ActivatePartnerApiProductionMandate;
use LBHurtado\XChange\Actions\PartnerApi\ApprovePartnerApiProductionMandate;
use LBHurtado\XChange\Actions\PartnerApi\RequestPartnerApiProductionMandate;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ActivatePartnerApiProductionMandateRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApprovePartnerApiProductionMandateRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StorePartnerApiProductionMandateRequest;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use Throwable;

final class CockpitPartnerApiProductionMandateController extends Controller
{
    public function store(StorePartnerApiProductionMandateRequest $request, RequestPartnerApiProductionMandate $action, WalletAccessContract $wallets): JsonResponse
    {
        $validated = $request->validated();
        $modelClass = (string) config('auth.providers.users.model');
        abort_unless(is_subclass_of($modelClass, Model::class), 422, 'The Account model is unavailable.');
        $issuer = $modelClass::query()->find($validated['issuer_id']);
        abort_unless($issuer instanceof Model, 422, 'The selected issuer Account is unavailable.');
        try {
            $wallets->resolveForUser($issuer);
        } catch (Throwable) {
            throw ValidationException::withMessages(['issuer_id' => ['The selected issuer does not have an active X-Change Account.']]);
        }
        $mandate = $action->handle(
            $validated['name'],
            $issuer,
            $validated['scopes'],
            [
                'currencies' => $validated['currencies'],
                'settlement_rails' => $validated['settlement_rails'],
                'unbound_pay_codes' => $validated['unbound_pay_codes'],
                'maximum_amount_minor' => $validated['maximum_amount_minor'],
                'daily_principal_limit_minor' => $validated['daily_principal_limit_minor'],
                'voucher_profiles' => $validated['voucher_profiles'] ?? ['disbursement'],
                'stored_value_spend' => $validated['stored_value_spend'] ?? ['enabled' => false],
            ],
            $request->user(),
        );

        return response()->json(['reference' => $mandate->reference, 'status' => $mandate->status->value], 201);
    }

    public function approve(ApprovePartnerApiProductionMandateRequest $request, PartnerApiProductionMandate $partnerApiProductionMandate, ApprovePartnerApiProductionMandate $action): JsonResponse
    {
        $mandate = $action->handle($partnerApiProductionMandate, $request->user());

        return response()->json(['reference' => $mandate->reference, 'status' => $mandate->status->value]);
    }

    public function activate(ActivatePartnerApiProductionMandateRequest $request, PartnerApiProductionMandate $partnerApiProductionMandate, ActivatePartnerApiProductionMandate $action): JsonResponse
    {
        $credential = $action->handle($partnerApiProductionMandate, $request->user());

        return response()->json([
            'schema' => 'x-change.partner-api-credential.v1',
            'reference' => $credential->reference,
            'client_id' => $credential->client_id,
            'client_secret' => $credential->client_secret,
            'environment' => $credential->environment,
            'scopes' => $credential->scopes,
            'mandate' => $credential->mandate,
            'secret_display' => 'one_time_only',
        ], 201, ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }
}
