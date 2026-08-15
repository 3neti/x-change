<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Provisioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XProvisioning\Enums\ProvisioningRequestStatus;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class ProvisioningInvitationPageController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $offer = $this->resolveOffer($token);
        $revision = $offer->revision;
        $operator = $request->user();

        if (! $operator instanceof Model) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('x-change/provisioning/Invitation', [
            'invitation' => [
                'profile' => $offer->request->profile->value,
                'label' => (string) data_get($revision->snapshot, 'label', 'Provisioning Invitation'),
                'purpose' => (string) data_get($revision->snapshot, 'purpose', ''),
                'status' => $offer->status->value,
                'required_evidence' => array_values((array) data_get($revision->snapshot, 'required_evidence', [])),
                'expires_at' => $offer->expires_at?->toIso8601String(),
                'authenticated' => $operator instanceof Model,
                'can_accept' => $operator instanceof Model
                    && $offer->status === ProvisioningRequestStatus::Offered
                    && ! $offer->expires_at?->isPast(),
                'accept_url' => route('x-change.provisioning.claim.accept', ['token' => $token]),
                'login_url' => Route::has('login') ? route('login') : '/login',
            ],
        ]);
    }

    private function resolveOffer(string $token): ProvisioningOffer
    {
        abort_unless(strlen($token) === 64, 404);

        return ProvisioningOffer::query()
            ->with(['request', 'revision'])
            ->where('claim_token_hash', hash('sha256', $token))
            ->firstOrFail();
    }
}
