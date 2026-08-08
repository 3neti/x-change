<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartnerDestination;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerDestinationData;
use LBHurtado\XChange\Http\Requests\Cockpit\StoreCommercialPartnerDestinationRequest;
use LBHurtado\XChange\Models\CommercialPartner;

final class CockpitCommercialPartnerDestinationController extends Controller
{
    public function __construct(private readonly ManageCommercialPartnerDestination $destinations) {}

    public function store(
        StoreCommercialPartnerDestinationRequest $request,
        CommercialPartner $partner,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $validated = $request->validated();
        $draft = $this->destinations->createDraft($operator, $partner, new CommercialPartnerDestinationData(
            provider: (string) $validated['provider'],
            connectionReference: (string) $validated['connection_reference'],
            currency: (string) $validated['currency'],
            bankCode: (string) $validated['bank_code'],
            accountNumber: (string) $validated['account_number'],
            recipientName: (string) $validated['recipient_name'],
            mobile: (string) $validated['mobile'],
            authorizationReference: (string) $validated['authorization_reference'],
        ));
        $this->destinations->submit($operator, $draft);

        return back()->with('success', 'Partner destination submitted for independent approval.');
    }
}
