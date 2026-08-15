<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Cockpit\PartnerApiClientReadModel;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final class CockpitPartnerApiPageController extends Controller
{
    public function __construct(
        private readonly PartnerApiOperatorAuthority $authority,
        private readonly PartnerApiClientReadModel $readModel,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model && $this->authority->mayView($operator), 404);

        return Inertia::render('x-change/cockpit/ApiPartners', [
            'partner_api' => $this->readModel->build($operator),
            'partner_api_store_url' => route('x-change.cockpit.api-partners.clients.store'),
            'partner_api_production_store_url' => route('x-change.cockpit.api-partners.production-mandates.store'),
            'csrf_token' => csrf_token(),
        ]);
    }
}
