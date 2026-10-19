<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\FormFlowManager\Data\TerminalNavigation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\RiderRedirect;
use LBHurtado\XChange\Models\RiderRedirectEvent;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Support\Claim\ClaimPreviewExecutionGuard;
use LBHurtado\XRider\Services\RedirectDestinationPolicy;
use Symfony\Component\HttpFoundation\Response;

final class RiderRedirectEngine
{
    public function validate(array $input): void
    {
        if (config('rider-redirect.enabled') && filled(data_get($input, 'rider.url'))) {
            app(RedirectDestinationPolicy::class)->validate((string) data_get($input, 'rider.url'));
        }
    }

    public function commit(Voucher $voucher): void
    {
        $url = data_get($voucher->instructions?->toArray() ?? [], 'rider.url');
        if (! config('rider-redirect.enabled') || blank($url)
            || app(ClaimPreviewExecutionGuard::class)->isPreview($voucher)) {
            return;
        }
        if (RiderRedirect::query()->where('voucher_id', $voucher->getKey())->exists()) {
            return;
        }
        app(RedirectDestinationPolicy::class)->validate($url);
        DB::transaction(function () use ($voucher, $url): void {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $redirect = RiderRedirect::query()->firstOrCreate(['voucher_id' => $voucher->getKey()], [
                'reference' => (string) Str::uuid(),
                'destination' => $url,
                'destination_host' => $host,
                'fingerprint' => hash('sha256', $url),
                'policy' => ['version' => 1, 'trigger' => 'confirmed_claim_success', 'receipts' => filled(((array) config('rider-redirect.receipt_keys', []))[$host] ?? null)],
            ]);
            $this->event($redirect, 'committed', 'committed');
        });
    }

    public function eligible(VoucherClaim $claim): void
    {
        if (! in_array($claim->status, ['succeeded', 'withdrawn', 'redeemed', 'settled'], true)
            || $this->unsettled((array) $claim->meta)
            || $this->unsettled((array) $claim->voucher?->metadata)) {
            return;
        }
        DB::transaction(function () use ($claim): void {
            $redirect = RiderRedirect::query()->where('voucher_id', $claim->voucher_id)->lockForUpdate()->first();
            if ($redirect === null) {
                return;
            }
            if ($redirect->eligible_at === null) {
                $redirect->update(['eligible_claim_id' => $claim->getKey(), 'eligible_at' => now()]);
            }
            $this->event($redirect, 'eligible', 'eligible:'.$claim->getKey(), context: ['claim_id' => $claim->getKey()]);
        });
    }

    public function authorizeSession(Voucher $voucher, Request $request): void
    {
        if ($request->hasSession() && RiderRedirect::query()->where('voucher_id', $voucher->getKey())->exists()) {
            if (! $this->authorized($voucher, $request)) {
                $request->session()->put('rider_redirect_authorized.'.$voucher->getKey(), Str::random(64));
            }
            $request->session()->save();
        }
    }

    public function bindClaimSession(VoucherClaim $claim, Request $request): void
    {
        $token = $request->hasSession() ? $request->session()->get('rider_redirect_authorized.'.$claim->voucher_id) : null;
        if (! is_string($token) || strlen($token) !== 64) {
            return;
        }
        $meta = (array) $claim->meta;
        $meta['rider_redirect_session'] ??= hash('sha256', $token);
        $claim->meta = $meta;
    }

    public function completion(Voucher $voucher, Request $request): ?Response
    {
        $redirect = RiderRedirect::query()->where('voucher_id', $voucher->getKey())->first();
        if ($redirect === null || ! $this->authorized($voucher, $request) || ! $this->isEligible($redirect, $request)) {
            return null;
        }

        return (new TerminalNavigation(route('x-change.claim.redirect', ['code' => $voucher->code])))->toResponse($request);
    }

    public function handoff(Voucher $voucher, Request $request): Response
    {
        abort_unless($this->authorized($voucher, $request), 403);
        $redirect = RiderRedirect::query()->where('voucher_id', $voucher->getKey())->firstOrFail();
        abort_unless($this->isEligible($redirect, $request), 409);
        $attempt = (string) Str::uuid();
        $claimId = VoucherClaim::query()->where('voucher_id', $voucher->getKey())->where('meta->rider_redirect_session', $this->sessionFingerprint($voucher, $request))->latest('id')->value('id');
        DB::transaction(function () use ($redirect, $attempt, $claimId): void {
            $this->event($redirect, 'handoff_requested', $attempt.':requested', $attempt, ['claim_id' => $claimId]);
            $this->event($redirect, 'redirect_prepared', $attempt.':prepared', $attempt, ['claim_id' => $claimId]);
        });
        $url = $redirect->destination;
        if (data_get($redirect->policy, 'receipts')) {
            [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
            $url = $base.(str_contains($base, '?') ? '&' : '?').'x_rider_handoff='.rawurlencode($attempt).($fragment === null ? '' : '#'.$fragment);
        }

        return (new TerminalNavigation($url))->toResponse($request);
    }

    public function summary(Voucher $voucher): ?array
    {
        $redirect = RiderRedirect::query()->where('voucher_id', $voucher->getKey())->first();
        if ($redirect === null) {
            return null;
        }

        return ['reference' => $redirect->reference, 'destination_host' => $redirect->destination_host,
            'eligible_at' => $redirect->eligible_at?->toIso8601String(),
            'pending_journal_events' => RiderRedirectEvent::query()->where('redirect_id', $redirect->getKey())->whereNull('delivered_at')->count(),
            'events' => RiderRedirectEvent::query()->where('redirect_id', $redirect->getKey())->orderBy('id')->get(['event_type', 'occurred_at', 'attempt_reference'])->toArray()];
    }

    public function authorized(Voucher $voucher, Request $request): bool
    {
        return $this->sessionFingerprint($voucher, $request) !== null;
    }

    public function sessionFingerprint(Voucher $voucher, Request $request): ?string
    {
        $token = $request->hasSession() ? $request->session()->get('rider_redirect_authorized.'.$voucher->getKey()) : null;

        return is_string($token) && strlen($token) === 64 ? hash('sha256', $token) : null;
    }

    private function isEligible(RiderRedirect $redirect, Request $request): bool
    {
        $voucher = Voucher::query()->findOrFail($redirect->voucher_id);
        $fingerprint = $this->sessionFingerprint($voucher, $request);
        if ($fingerprint === null || $redirect->eligible_at === null
            || $this->unsettled((array) $voucher->metadata)) {
            return false;
        }
        $claim = VoucherClaim::query()->where('voucher_id', $redirect->voucher_id)
            ->where('meta->rider_redirect_session', $fingerprint)->latest('id')->first();

        return $claim !== null && in_array($claim->status, ['succeeded', 'withdrawn', 'redeemed', 'settled'], true)
            && ! $this->unsettled((array) $claim->meta);
    }

    /** @param array<string, mixed> $metadata */
    private function unsettled(array $metadata): bool
    {
        return data_get($metadata, 'disbursement.requires_reconciliation') === true
            || data_get($metadata, 'disbursement.requires_recovery') === true
            || in_array(strtolower((string) data_get($metadata, 'disbursement.status', '')), ['pending', 'processing', 'queued', 'in_progress', 'for_reconciliation', 'reconciling', 'failed', 'rejected', 'declined', 'cancelled'], true);
    }

    public function event(RiderRedirect $redirect, string $type, string $key, ?string $attempt = null, array $context = []): RiderRedirectEvent
    {
        return RiderRedirectEvent::query()->firstOrCreate(['event_key' => $redirect->reference.':'.$key], [
            'reference' => (string) Str::uuid(), 'redirect_id' => $redirect->getKey(),
            'event_type' => 'claim.rider_redirect.'.$type, 'attempt_reference' => $attempt,
            'payload' => ['obligation_reference' => $redirect->reference, 'voucher_id' => $redirect->voucher_id, 'destination_host' => $redirect->destination_host, 'destination_fingerprint' => $redirect->fingerprint, 'policy_version' => data_get($redirect->policy, 'version')] + $context,
            'occurred_at' => now(),
        ]);
    }
}
