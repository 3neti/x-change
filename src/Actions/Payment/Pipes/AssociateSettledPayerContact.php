<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment\Pipes;

use Closure;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\SettledPaymentProcess;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Checkout\ReverifyCheckoutQrPayer;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Propaganistas\LaravelPhone\PhoneNumber;

final readonly class AssociateSettledPayerContact
{
    public function __construct(
        private CheckoutLifecycle $checkouts,
        private ReverifyCheckoutQrPayer $reverifyQrPayer,
    ) {}

    public function handle(SettledPaymentProcess $process, Closure $next): mixed
    {
        if ($process->contact_id !== null) {
            return $next($process);
        }

        $observation = ProviderFundingObservation::query()->findOrFail($process->observation_id);

        if ($process->flow === 'public_auto_generate' && $process->payment_type === 'funding_settlement') {
            $settlement = FundingSettlement::query()->findOrFail($process->payment_id);
            $order = PayCodeIssuanceFundingOrder::query()
                ->where('funding_intent_id', $settlement->funding_intent_id)->first();

            if ($order !== null) {
                $this->checkouts->settled($order, $observation);
                $checkout = Checkout::query()->where('funding_order_id', $order->getKey())->first();

                if ($checkout?->contact_id === null && $checkout?->selected_method === 'qr_ph') {
                    $this->reverifyQrPayer->handle($order, $observation);
                    $checkout->refresh();
                }

                if ($checkout?->contact_id !== null) {
                    $process->forceFill([
                        'contact_id' => $checkout->contact_id,
                        'contact_source' => $checkout->contact_source,
                    ])->save();

                    return $next($process);
                }
            }
        }
        $institution = strtoupper(trim((string) $observation->payer_institution_ciphertext));
        $account = trim((string) $observation->payer_account_ciphertext);
        $wallet = in_array($institution, ['GXCHPHM2XXX', 'PAPHPHM1XXX', 'GCASH', 'MAYA', 'PAYMAYA'], true)
            && preg_match('/^(?:0|63|\+63)9[0-9]{9}$/', $account) === 1;
        $mobile = MobileNumber::normalize((string) $observation->payer_mobile_ciphertext);

        if ($mobile === null && $wallet) {
            $mobile = MobileNumber::normalize($account);
        }

        if (is_string($mobile) && preg_match('/^639[0-9]{9}$/', $mobile) === 1) {
            $contact = Contact::fromPhoneNumber(new PhoneNumber($mobile, 'PH'));
            $name = trim((string) $observation->payer_name_ciphertext);

            if ($name !== '' && trim((string) $contact->name) === '') {
                $contact->name = $name;
                $contact->save();
            }

            $process->forceFill([
                'contact_id' => $contact->getKey(),
                'contact_source' => $observation->payer_identity_provider_verified ? 'provider_verified' : 'provider_reported',
            ])->save();
        } else {
            $process->forceFill(['contact_source' => 'unavailable'])->save();
        }

        return $next($process);
    }
}
