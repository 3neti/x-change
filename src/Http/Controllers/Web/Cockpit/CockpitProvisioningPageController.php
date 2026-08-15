<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Services\Provisioning\CockpitProvisioningReadModel;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;

final class CockpitProvisioningPageController extends Controller
{
    public function __construct(
        private readonly ProvisioningOperatorAuthority $authority,
        private readonly CockpitProvisioningReadModel $readModel,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operator = $request->user();
        abort_unless(
            $operator instanceof Model
                && $this->authority->allows($operator, ProvisioningOperatorCapability::View),
            404,
        );

        return Inertia::render('x-change/cockpit/Provisioning', [
            'provisioning' => $this->readModel->build($operator),
            'provisioning_store_url' => route('x-change.cockpit.provisioning.requests.store'),
            'csrf_token' => csrf_token(),
        ]);
    }
}
