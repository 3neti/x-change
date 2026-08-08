<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartner;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerRevisionData;
use LBHurtado\XChange\Http\Requests\Cockpit\StoreCommercialPartnerRequest;

final class CockpitCommercialPartnerController extends Controller
{
    public function __construct(private readonly ManageCommercialPartner $partners) {}

    public function store(StoreCommercialPartnerRequest $request): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $validated = $request->validated();
        $draft = $this->partners->createDraft($operator, new CommercialPartnerRevisionData(
            reference: (string) $validated['reference'],
            displayName: (string) $validated['display_name'],
            legalName: filled($validated['legal_name'] ?? null) ? (string) $validated['legal_name'] : null,
            externalReference: filled($validated['external_reference'] ?? null)
                ? (string) $validated['external_reference']
                : null,
            attributionBasis: (string) $validated['attribution_basis'],
            authorizationReference: (string) $validated['authorization_reference'],
            terms: (array) ($validated['terms'] ?? []),
        ));
        $this->partners->submit($operator, $draft);

        return back()->with('success', 'Commercial Partner submitted for independent approval.');
    }
}
