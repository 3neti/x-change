<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;

final class ClaimAuthorizationRequiredController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        ClaimWorkflowResolverContract $workflows,
        ClaimEntryResponseFactory $responses,
        ClaimAuthenticationIntent $loginIntent,
    ): Response|RedirectResponse {
        $code = strtoupper(trim($code));
        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher instanceof Voucher) {
            return $responses->error(
                message: 'Invalid Pay Code.',
                code: $code,
            );
        }

        $workflow = $workflows->resolve($voucher);

        if (
            $workflow->authentication_mode !== ClaimAuthenticationMode::AuthenticatedOfficer
            && $workflow->key !== 'account-funding.v1'
        ) {
            return redirect()->route('x-change.claim.show', ['code' => $code]);
        }

        if ($request->user() !== null) {
            return redirect()->route('x-change.claim.show', ['code' => $code]);
        }

        $intent = $loginIntent->remember($request, $code, $workflow);

        return Inertia::render('x-change/claim/AuthRequired', [
            'code' => $code,
            'login_url' => Route::has('login') ? route('login') : '/login',
            'claim_url' => route('x-change.claim.show', ['code' => $code]),
            'intent' => $intent,
            'workflow' => [
                'key' => $workflow->key,
                'title' => $workflow->title,
                'description' => $workflow->description,
                'review' => $workflow->review,
            ],
        ])->rootView('x-change::claim-root');
    }
}
