<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Checkout;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Propaganistas\LaravelPhone\PhoneNumber;

final readonly class CheckoutLifecycle
{
    public function place(PayCodeIssuanceFundingOrder $order, ?Checkout $draft = null): Checkout
    {
        return DB::transaction(function () use ($order, $draft): Checkout {
            $existing = Checkout::query()->where('funding_order_id', $order->getKey())->first();

            if ($existing instanceof Checkout) {
                return $existing;
            }

            if ($draft instanceof Checkout) {
                $checkout = Checkout::query()->lockForUpdate()->findOrFail($draft->getKey());

                if ($checkout->status !== 'draft' || $checkout->funding_order_id !== null) {
                    abort(409, 'This checkout draft has already been placed.');
                }

                $checkout->forceFill([
                    'funding_order_id' => $order->getKey(),
                    'status' => 'placed',
                    'instructions_ciphertext' => $order->instructions_ciphertext,
                    'pricing_snapshot_ciphertext' => $order->pricing_snapshot_ciphertext,
                    'placed_at' => now(),
                ])->save();
                $this->event($checkout, 'placed', self::class, null, [
                    'funding_order_reference' => $order->reference,
                ]);

                return $checkout;
            }

            $checkout = Checkout::query()->firstOrCreate(
                ['funding_order_id' => $order->getKey()],
                [
                    'source' => data_get($order->metadata, 'source', 'legacy.on-demand-issuance'),
                    'owner_type' => $order->issuer_type,
                    'owner_id' => $order->issuer_id,
                    'status' => 'placed',
                    'selected_method' => 'qr_ph',
                    'instructions_ciphertext' => $order->instructions_ciphertext,
                    'pricing_snapshot_ciphertext' => $order->pricing_snapshot_ciphertext,
                    'placed_at' => $order->created_at ?? now(),
                ],
            );

            if ($checkout->wasRecentlyCreated) {
                $this->event($checkout, 'placed', self::class, null, [
                    'funding_order_reference' => $order->reference,
                ]);
            }

            return $checkout;
        });
    }

    public function selectBankTransfer(Checkout $checkout, string $mobile): Checkout
    {
        $normalized = MobileNumber::normalize($mobile);

        if ($normalized === null || preg_match('/^639[0-9]{9}$/', $normalized) !== 1) {
            throw ValidationException::withMessages(['mobile' => ['Enter a valid Philippine mobile number.']]);
        }

        return DB::transaction(function () use ($checkout, $normalized): Checkout {
            $locked = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());
            $this->assertOpen($locked);
            $locked->forceFill([
                'selected_method' => 'bank_transfer',
                'visitor_mobile_ciphertext' => $normalized,
                'visitor_mobile_hash' => hash_hmac('sha256', $normalized, (string) config('app.key')),
            ])->save();
            $this->event($locked, 'bank_transfer_selected', 'guest', null, [
                'mobile_last_four' => substr($normalized, -4),
            ]);

            return $locked;
        });
    }

    public function selectQrPh(Checkout $checkout): Checkout
    {
        return DB::transaction(function () use ($checkout): Checkout {
            $locked = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());
            $this->assertOpen($locked);
            if ($locked->selected_method === 'qr_ph') {
                return $locked;
            }
            $locked->forceFill(['selected_method' => 'qr_ph'])->save();
            $this->event($locked, 'qr_ph_selected', 'guest');

            return $locked;
        });
    }

    public function settled(PayCodeIssuanceFundingOrder $order, ProviderFundingObservation $observation): void
    {
        $checkout = $this->markSettled($order, $observation);
        DB::transaction(function () use ($checkout, $observation): void {
            $checkout = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());

            if (in_array($checkout->status, ['settled', 'issued'], true) && $checkout->contact_source !== null) {
                return;
            }

            $this->associateConfirmedContact($checkout, $observation);
        });
    }

    public function markSettled(PayCodeIssuanceFundingOrder $order, ProviderFundingObservation $observation): Checkout
    {
        $checkout = $this->place($order);

        return DB::transaction(function () use ($checkout, $observation): Checkout {
            $locked = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());

            if (! $locked->events()->where('event_type', 'payment_settled')->exists()) {
                if ($locked->status !== 'issued') {
                    $locked->forceFill(['status' => 'settled'])->save();
                }
                $this->event($locked, 'payment_settled', self::class, null, [
                    'observation_id' => $observation->getKey(),
                ]);
            }

            return $locked;
        });
    }

    private function associateConfirmedContact(Checkout $checkout, ProviderFundingObservation $observation): void
    {
        $providerMobile = MobileNumber::normalize($observation->payer_mobile_ciphertext);
        $providerMobile = is_string($providerMobile) && preg_match('/^639[0-9]{9}$/', $providerMobile) === 1
            ? $providerMobile : null;
        if ($providerMobile === null && $checkout->selected_method === 'qr_ph') {
            $institution = strtoupper(trim((string) $observation->payer_institution_ciphertext));
            $account = trim((string) $observation->payer_account_ciphertext);
            $walletMobile = MobileNumber::normalize($account);

            if (in_array($institution, ['GXCHPHM2XXX', 'PAPHPHM1XXX', 'GCASH', 'MAYA', 'PAYMAYA'], true)
                && preg_match('/^(?:0|63|\+63)9[0-9]{9}$/', $account) === 1
                && preg_match('/^639[0-9]{9}$/', (string) $walletMobile) === 1) {
                $providerMobile = $walletMobile;
            }
        }
        $visitorMobile = $checkout->selected_method === 'bank_transfer'
            ? $checkout->visitor_mobile_ciphertext : null;
        $mobile = $providerMobile ?? $visitorMobile;
        $source = $providerMobile === null
            ? 'visitor_supplied'
            : ($observation->payer_identity_provider_verified === true ? 'provider_verified' : 'provider_reported');

        if (is_string($mobile) && preg_match('/^639[0-9]{9}$/', $mobile) === 1) {
            $contact = Contact::fromPhoneNumber(new PhoneNumber($mobile, 'PH'));

            if ($contact instanceof Contact) {
                $providerName = trim((string) $observation->payer_name_ciphertext);

                if ($providerMobile !== null && $providerName !== '' && trim((string) $contact->name) === '') {
                    $contact->name = $providerName;
                    $contact->save();
                }

                $checkout->forceFill([
                    'contact_id' => $contact->getKey(),
                    'contact_source' => $source,
                ]);
            }
        }

        if ($checkout->contact_id === null) {
            $checkout->contact_source = 'unavailable';
        }
        if ($checkout->status !== 'issued') {
            $checkout->status = 'settled';
        }
        $checkout->save();
        $this->event($checkout, 'contact_association_completed', self::class, null, [
            'observation_id' => $observation->getKey(),
            'contact_source' => $checkout->contact_source,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function event(Checkout $checkout, string $type, string $actorType, ?string $actorId = null, array $metadata = []): void
    {
        $checkout->events()->create([
            'event_type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function assertOpen(Checkout $checkout): void
    {
        $order = $checkout->fundingOrder;

        if ($order === null || ! in_array($order->status->value, [
            'awaiting_payment', 'payer_acknowledged', 'verifying',
        ], true) || $order->expires_at->isPast()) {
            abort(409, 'This checkout no longer accepts a funding method change.');
        }
    }
}
