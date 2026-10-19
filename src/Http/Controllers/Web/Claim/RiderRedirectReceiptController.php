<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\RiderRedirect;
use LBHurtado\XChange\Models\RiderRedirectEvent;
use LBHurtado\XChange\Services\Claim\RiderRedirectEngine;

final class RiderRedirectReceiptController
{
    public function __invoke(Request $request, RiderRedirectEngine $engine): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'uuid'], 'event' => ['required', 'in:destination_received,page_initialized']]);
        $attempt = RiderRedirectEvent::query()->where('attempt_reference', $data['reference'])->where('event_type', 'claim.rider_redirect.redirect_prepared')->firstOrFail();
        $redirect = RiderRedirect::query()->findOrFail($attempt->redirect_id);
        $keys = (array) config('rider-redirect.receipt_keys', []);
        $key = $keys[$redirect->destination_host] ?? null;
        $timestamp = $request->header('X-Rider-Timestamp', '');
        abort_unless(is_string($key) && $key !== '' && ctype_digit($timestamp)
            && abs(time() - (int) $timestamp) <= (int) config('rider-redirect.receipt_clock_skew_seconds', 300)
            && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $key), $request->header('X-Rider-Signature', '')), 403);
        abort_unless(data_get($redirect->policy, 'receipts') === true, 403);
        DB::transaction(function () use ($engine, $redirect, $data, $attempt): void {
            $engine->event($redirect, $data['event'], $data['reference'].':'.$data['event'], $data['reference'], ['claim_id' => data_get($attempt->payload, 'claim_id')]);
        });

        return response()->json(['recorded' => true])->header('Cache-Control', 'no-store');
    }
}
