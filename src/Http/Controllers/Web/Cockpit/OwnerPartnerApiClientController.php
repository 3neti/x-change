<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Actions\PartnerApi\ChangePartnerApiClientStatus;
use LBHurtado\XChange\Actions\PartnerApi\CreateOwnerPartnerApiClient;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreOwnerPartnerApiClientRequest;
use LBHurtado\XChange\Models\PartnerApiClient;

final class OwnerPartnerApiClientController extends Controller
{
    public function index(Request $request): Response
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);
        $clients = PartnerApiClient::query()
            ->where('issuer_type', $owner->getMorphClass())
            ->where('issuer_id', (string) $owner->getKey())
            ->latest()->get()->map(fn (PartnerApiClient $client): array => [
                'reference' => $client->reference,
                'name' => $client->name,
                'status' => $client->status->value,
                'scopes' => $client->scopes,
                'suspend_url' => route('x-change.cockpit.owner-api-clients.suspend', $client),
                'revoke_url' => route('x-change.cockpit.owner-api-clients.revoke', $client),
            ])->all();

        return Inertia::render('x-change/cockpit/OwnerApiClients', [
            'clients' => $clients,
            'scopes' => config('x-change.partner_api.scopes', []),
            'store_url' => route('x-change.cockpit.owner-api-clients.store'),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function store(StoreOwnerPartnerApiClientRequest $request, CreateOwnerPartnerApiClient $create): JsonResponse
    {
        $input = $request->validated();
        try {
            $credential = $create->handle($request->user(), $input['name'], $input['scopes'], [
                'currencies' => $input['currencies'],
                'settlement_rails' => $input['settlement_rails'],
                'maximum_amount_minor' => $input['maximum_amount_minor'],
                'daily_principal_limit_minor' => $input['daily_principal_limit_minor'],
                'unbound_pay_codes' => $input['unbound_pay_codes'],
                'voucher_profiles' => $input['voucher_profiles'] ?? ['disbursement'],
                'stored_value_spend' => $input['stored_value_spend'] ?? ['enabled' => false],
            ]);
        } catch (PayCodeWalletNotResolved $exception) {
            throw ValidationException::withMessages(['account' => [$exception->getMessage()]]);
        }

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

    public function suspend(Request $request, PartnerApiClient $client, ChangePartnerApiClientStatus $change): RedirectResponse
    {
        abort_unless($request->user() instanceof Model, 403);
        $change->suspendOwned($client, $request->user());

        return back();
    }

    public function revoke(Request $request, PartnerApiClient $client, ChangePartnerApiClientStatus $change): RedirectResponse
    {
        abort_unless($request->user() instanceof Model, 403);
        $change->revokeOwned($client, $request->user());

        return back();
    }
}
