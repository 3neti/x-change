<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use Throwable;

final readonly class PartnerPaymentEventDelivery
{
    public function __construct(private PartnerPaymentReceiver $receivers) {}

    public function deliver(int $id): bool
    {
        if (! config('x-change.partner_api.payment_events.enabled', false)) {
            return false;
        }
        $event = DB::transaction(function () use ($id): ?PartnerPaymentEvent {
            $event = PartnerPaymentEvent::query()->lockForUpdate()->find($id);
            if (! $event || in_array($event->status, ['delivered', 'failed'], true) || $event->available_at->isFuture()
                || ($event->status === 'sending' && $event->lease_expires_at?->isFuture())) {
                return null;
            }
            if ($event->attempts >= 8) {
                $event->update(['status' => 'failed', 'last_error' => 'attempts_exhausted']);

                return null;
            }
            $event->update(['status' => 'sending', 'attempts' => $event->attempts + 1, 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addMinutes(2)]);

            return $event;
        });
        if (! $event) {
            return false;
        }
        $status = null;
        $error = 'delivery_unavailable';
        $success = false;
        try {
            $client = PartnerApiClient::query()->find($event->partner_api_client_id);
            if (! $client || ! $client->isActive() || $client->reference !== $event->partner_reference) {
                $error = 'partner_binding_inactive';
            } else {
                $receiver = $this->receivers->resolve($event->partner_reference);
                $timestamp = (string) now()->timestamp;
                $response = Http::withoutRedirecting()->connectTimeout(3)->timeout(10)
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => [$receiver['resolve']]], 'proxy' => '', 'verify' => true])
                    ->withHeaders([
                        'X-XChange-Timestamp' => $timestamp,
                        'X-XChange-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$event->body, $receiver['secret']),
                    ])->withBody($event->body, 'application/json')->post($receiver['url']);
                $status = $response->status();
                $success = in_array($status, [200, 202], true);
                $error = $success ? null : 'receiver_http_error';
            }
        } catch (Throwable) {
            // Persist only an allowlisted diagnostic, never response bodies or transport secrets.
        }
        PartnerPaymentEvent::query()->whereKey($event->getKey())->where('lease_token', $event->lease_token)->update([
            'status' => $success ? 'delivered' : ($event->attempts >= 8 ? 'failed' : 'pending'),
            'available_at' => now()->addSeconds(min(3600, 30 * (2 ** ($event->attempts - 1)))),
            'lease_token' => null,
            'lease_expires_at' => null,
            'delivered_at' => $success ? now() : null,
            'last_http_status' => $status,
            'last_error' => $error,
        ]);

        return $success;
    }
}
