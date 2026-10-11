<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Checkout;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use LBHurtado\XChange\Actions\Funding\RequestOnDemandPayCodeIssuanceRetry;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleAccess;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleReadModel;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CheckoutConsoleController extends Controller
{
    public function show(Request $request, ConfiguredCommercialPrincipalResolver $resolver, CheckoutConsoleAccess $access, CheckoutConsoleReadModel $read): HttpResponse
    {
        $principal = $resolver->resolve();
        $owner = $access->owner($request, $principal);
        $status = $request->query('status');

        return Inertia::render('x-change/checkout/Console', [
            'role' => $owner ? 'owner' : 'locked',
            'monitor' => $owner ? $read->forOwner($principal, is_string($status) ? $status : null) : null,
        ])->toResponse($request)->withHeaders($this->responseHeaders());
    }

    public function viewer(Request $request, string $token, ConfiguredCommercialPrincipalResolver $resolver, CheckoutConsoleAccess $access, CheckoutConsoleReadModel $read): HttpResponse
    {
        $principal = $resolver->resolve();
        abort_unless($access->viewer($token, $principal), 404);
        $access->audit($request, $principal, 'viewer_opened');
        $status = $request->query('status');

        return Inertia::render('x-change/checkout/Console', [
            'role' => 'viewer',
            'viewer_token' => $token,
            'monitor' => $read->forOwner($principal, is_string($status) ? $status : null),
        ])->toResponse($request)->withHeaders($this->responseHeaders());
    }

    public function unlock(Request $request, ConfiguredCommercialPrincipalResolver $resolver, CheckoutConsoleAccess $access): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'max:512']]);
        $principal = $resolver->resolve();

        if (! $access->unlock($request, $principal, $validated['password'])) {
            throw ValidationException::withMessages(['password' => ['The console password is invalid or unavailable.']]);
        }

        return redirect()->route('x-change.checkout.show');
    }

    public function lock(Request $request, ConfiguredCommercialPrincipalResolver $resolver, CheckoutConsoleAccess $access): RedirectResponse
    {
        $principal = $resolver->resolve();
        if ($access->owner($request, $principal)) {
            $access->audit($request, $principal, 'owner_locked');
        }
        $request->session()->forget('x-change.checkout.owner');
        $request->session()->regenerate();

        return redirect()->route('x-change.checkout.show');
    }

    public function retry(
        Request $request,
        Checkout $checkout,
        ConfiguredCommercialPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
        CheckoutLifecycle $lifecycle,
        RequestOnDemandPayCodeIssuanceRetry $retry,
    ): RedirectResponse {
        $this->authorizeOwner($request, $checkout, $resolver, $access);
        $order = $checkout->fundingOrder;
        abort_unless($order !== null, 404);
        $retry->handle($order, 'checkout_console', $this->sessionActor($request));
        $lifecycle->event($checkout, 'operator_retry_requested', 'checkout_console', $this->sessionActor($request), $this->audit($request));

        return back();
    }

    public function openRefund(
        Request $request,
        Checkout $checkout,
        ConfiguredCommercialPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
        CheckoutLifecycle $lifecycle,
    ): RedirectResponse {
        $this->authorizeOwner($request, $checkout, $resolver, $access);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);

        DB::transaction(function () use ($request, $checkout, $validated, $lifecycle): void {
            $order = PayCodeIssuanceFundingOrder::query()
                ->with('fundingIntent.settlement')
                ->lockForUpdate()
                ->findOrFail($checkout->funding_order_id);
            $locked = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());
            $settlement = $order->fundingIntent?->settlement;

            if ($order->status->value !== 'issuance_attention'
                || $order->voucher_id !== null
                || $settlement === null
                || $locked->refundCase()->exists()
                || data_get($order->metadata, 'issuance_retry.pending') === true) {
                throw new ConflictHttpException('This checkout is not eligible for a manual refund case.');
            }

            CheckoutRefundCase::query()->create([
                'checkout_id' => $locked->getKey(),
                'funding_settlement_id' => $settlement->getKey(),
                'status' => 'open',
                'amount_minor' => $settlement->gross_amount_minor,
                'currency' => $settlement->currency,
                'reason' => $validated['reason'],
                'opened_by' => $this->sessionActor($request),
            ]);
            $lifecycle->event($locked, 'manual_refund_case_opened', 'checkout_console', $this->sessionActor($request), $this->audit($request));
        });

        return back();
    }

    public function recordRefund(
        Request $request,
        Checkout $checkout,
        ConfiguredCommercialPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
        CheckoutLifecycle $lifecycle,
    ): RedirectResponse {
        $this->authorizeOwner($request, $checkout, $resolver, $access);
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'external_reference' => ['required', 'string', 'max:191'],
        ]);
        abort_unless($access->reconfirm($validated['password']), 403);

        DB::transaction(function () use ($request, $checkout, $validated, $lifecycle): void {
            $case = CheckoutRefundCase::query()->where('checkout_id', $checkout->getKey())->lockForUpdate()->firstOrFail();

            if ($case->status !== 'open') {
                throw new ConflictHttpException('The refund case has already been updated.');
            }

            $case->forceFill([
                'status' => 'external_return_recorded',
                'external_reference_ciphertext' => $validated['external_reference'],
                'disposed_by' => $this->sessionActor($request),
                'disposed_at' => now(),
            ])->save();
            $lifecycle->event($checkout, 'external_refund_recorded', 'checkout_console', $this->sessionActor($request), $this->audit($request));
        });

        return back();
    }

    public function reconcileRefund(
        Request $request,
        Checkout $checkout,
        ConfiguredCommercialPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
        CheckoutLifecycle $lifecycle,
    ): RedirectResponse {
        $this->authorizeOwner($request, $checkout, $resolver, $access);
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'treasury_reference' => ['required', 'string', 'max:191'],
        ]);
        abort_unless($access->reconfirm($validated['password']), 403);

        DB::transaction(function () use ($request, $checkout, $validated, $lifecycle): void {
            $case = CheckoutRefundCase::query()->where('checkout_id', $checkout->getKey())->lockForUpdate()->firstOrFail();

            if ($case->status !== 'external_return_recorded') {
                throw new ConflictHttpException('An external refund must be recorded first.');
            }

            $case->forceFill([
                'status' => 'reconciled',
                'treasury_reconciliation_reference' => $validated['treasury_reference'],
            ])->save();
            $lifecycle->event($checkout, 'refund_reconciled', 'checkout_console', $this->sessionActor($request), [
                ...$this->audit($request),
                'treasury_reference' => $validated['treasury_reference'],
            ]);
        });

        return back();
    }

    private function authorizeOwner(Request $request, Checkout $checkout, ConfiguredCommercialPrincipalResolver $resolver, CheckoutConsoleAccess $access): void
    {
        $principal = $resolver->resolve();

        abort_unless($access->owner($request, $principal)
            && $checkout->source === 'public.auto-generate'
            && $checkout->owner_type === $principal::class
            && $checkout->owner_id === (string) $principal->getKey(), 404);
    }

    /** @return array{ip_hash: string, user_agent_hash: string} */
    private function audit(Request $request): array
    {
        return [
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
        ];
    }

    private function sessionActor(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    /** @return array<string, string> */
    private function responseHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
