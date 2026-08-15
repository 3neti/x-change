<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PartnerApi\ChangePartnerApiClientStatus;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RevokePartnerApiClientRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\SuspendPartnerApiClientRequest;
use LBHurtado\XChange\Models\PartnerApiClient;

final class CockpitPartnerApiClientStatusController extends Controller
{
    public function suspend(
        SuspendPartnerApiClientRequest $request,
        PartnerApiClient $partnerApiClient,
        ChangePartnerApiClientStatus $change,
    ): RedirectResponse {
        abort_unless($request->user() instanceof Model, 403);
        $change->suspend($partnerApiClient, $request->user());

        return back()->with('success', 'Partner API client suspended. Existing access tokens were revoked.');
    }

    public function revoke(
        RevokePartnerApiClientRequest $request,
        PartnerApiClient $partnerApiClient,
        ChangePartnerApiClientStatus $change,
    ): RedirectResponse {
        abort_unless($request->user() instanceof Model, 403);
        $change->revoke($partnerApiClient, $request->user());

        return back()->with('success', 'Partner API client revoked permanently.');
    }
}
