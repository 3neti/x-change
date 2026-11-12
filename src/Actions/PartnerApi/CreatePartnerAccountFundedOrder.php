<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateGuard;
use LBHurtado\XChange\Services\PartnerApi\PartnerIssuanceReservationService;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final readonly class CreatePartnerAccountFundedOrder
{
    public function __construct(
        private PartnerIssuanceReservationService $reservations,
        private PartnerApiMandateGuard $mandates,
        private EstimatePayCodeCost $estimate,
        private PrepareOnDemandPayCodeIssuance $prepare,
    ) {}

    /**
     * @param  array<string, mixed>  $instructions
     * @return array{order: PayCodeIssuanceFundingOrder, replayed: bool}
     */
    public function handle(PartnerApiClient $client, string $reference, string $key, array $instructions, string $method, ?string $payerMobile = null): array
    {
        $mobile = $method === 'bank_transfer' ? MobileNumber::normalize((string) $payerMobile) : null;
        if (! in_array($method, ['qr_ph', 'bank_transfer'], true)
            || ($method === 'bank_transfer' && (! is_string($mobile) || preg_match('/^639[0-9]{9}$/', $mobile) !== 1))) {
            throw ValidationException::withMessages(['funding_method' => ['Select a valid funding method and payer mobile number.']]);
        }
        $issuer = $client->issuer;
        if ($issuer === null) {
            throw ValidationException::withMessages(['issuer' => ['The Partner issuer Account is unavailable.']]);
        }
        data_set($instructions, 'metadata.issuer_id', (string) $issuer->getKey());
        data_set($instructions, 'metadata.custom.external_reference', $reference);
        data_set($instructions, '_meta.source', 'partner.account-funded-order');
        data_set($instructions, '_meta.partner_funding_method', $method);
        data_set($instructions, '_meta.partner_payer_fingerprint', $mobile === null ? null : hash_hmac('sha256', $mobile, (string) config('app.key')));
        $currency = strtoupper((string) data_get($instructions, 'cash.currency'));
        $principal = $this->mandates->principalMinor($instructions);

        try {
            return Cache::lock('x-change:partner-account-funding:'.$client->getKey(), 90)->block(10, function () use ($client, $reference, $key, $instructions, $currency, $principal, $issuer): array {
                $reservation = $this->reservations->reserve($client, $reference, $key, $instructions, $principal, $currency);
                $orderId = DB::table('x_change_partner_issuance_reservations')->where('id', $reservation['id'])->value('funding_order_id');
                if ($orderId !== null) {
                    return ['order' => PayCodeIssuanceFundingOrder::query()->findOrFail($orderId), 'replayed' => true];
                }
                $pricing = $this->estimate->handle($instructions);
                data_set($instructions, '_meta.partner_issuance_reservation_id', $reservation['id']);
                $order = $this->prepare->handle(
                    issuer: $issuer,
                    instructions: $instructions,
                    pricing: $pricing,
                    idempotencyKey: 'partner-account-funded:'.$client->reference.':'.$key,
                    fundingBasis: OnDemandIssuanceFundingBasis::Shortfall,
                );

                return ['order' => $order, 'replayed' => $reservation['replayed']];
            });
        } catch (LockTimeoutException) {
            abort(429, 'Another Partner funding request is in progress. Retry with the same idempotency key.');
        }
    }
}
