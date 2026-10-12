<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceInput;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceInstructionPolicy;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final readonly class CreatePartnerFundedOrder
{
    public function __construct(
        private ConfiguredCommercialPrincipalResolver $principals,
        private PublicIssuanceInput $input,
        private PublicIssuanceInstructionPolicy $policy,
        private EstimatePayCodeCost $estimate,
        private PrepareOnDemandPayCodeIssuance $prepare,
        private CheckoutLifecycle $checkouts,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{checkout: Checkout, replayed: bool}
     */
    public function handle(PartnerApiClient $client, array $validated, string $key): array
    {
        $method = (string) Arr::pull($validated, 'funding_method');
        $mobile = Arr::pull($validated, 'payer_mobile');
        $normalizedMobile = $method === 'bank_transfer'
            ? MobileNumber::normalize((string) $mobile) : null;

        if ($method === 'bank_transfer' && (! is_string($normalizedMobile)
            || preg_match('/^639[0-9]{9}$/', $normalizedMobile) !== 1)) {
            throw ValidationException::withMessages(['payer_mobile' => ['Enter a valid Philippine mobile number.']]);
        }

        $amountMinor = (int) round(((float) data_get($validated, 'cash.amount')) * 100);
        $publicInput = $this->input->validate([
            'amount_minor' => $amountMinor,
            'currency' => data_get($validated, 'cash.currency'),
        ]);
        data_set($validated, 'cash.currency', $publicInput['currency']);
        $this->policy->assertAllowed($validated);
        $principal = $this->principals->resolve();
        data_set($validated, 'metadata.issuer_id', (string) $principal->getKey());
        data_set($validated, '_meta.source', 'partner.funded-order');
        $keyHash = hash('sha256', $key);
        $fingerprint = hash('sha256', json_encode(
            [$validated, $method, $normalizedMobile],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ));

        try {
            return Cache::lock('x-change:partner-funded-order:'.$client->getKey(), 90)
                ->block(10, function () use ($client, $validated, $method, $normalizedMobile, $key, $keyHash, $fingerprint, $principal): array {
                    abort_unless(PartnerApiClient::query()->findOrFail($client->getKey())->isActive(), 401);
                    $existing = Checkout::query()
                        ->where('partner_api_client_id', $client->getKey())
                        ->where('api_idempotency_key_hash', $keyHash)
                        ->first();

                    if ($existing instanceof Checkout) {
                        if (! hash_equals((string) $existing->api_request_fingerprint, $fingerprint)) {
                            abort(409, 'The idempotency key is already bound to different funded-order instructions.');
                        }

                        return ['checkout' => $existing, 'replayed' => true];
                    }

                    $maximumOpen = (int) config('x-change.partner_api.funded_orders.maximum_open_per_client', 3);
                    $open = Checkout::query()
                        ->where('partner_api_client_id', $client->getKey())
                        ->whereHas('fundingOrder', static fn ($query) => $query->whereIn('status', [
                            'awaiting_payment', 'payer_acknowledged', 'verifying',
                        ])->where('expires_at', '>', now()))
                        ->count();

                    if ($open >= $maximumOpen) {
                        abort(429, 'The client has reached its limit of unpaid funded orders.');
                    }

                    $pricing = $this->estimate->handle($validated);
                    $order = $this->prepare->handle(
                        issuer: $principal,
                        instructions: $validated,
                        pricing: $pricing,
                        idempotencyKey: 'partner-funded:'.$client->reference.':'.$key,
                        fundingBasis: OnDemandIssuanceFundingBasis::FullAmount,
                    );
                    $checkout = $this->checkouts->place($order);
                    $checkout->forceFill([
                        'partner_api_client_id' => $client->getKey(),
                        'api_idempotency_key_hash' => $keyHash,
                        'api_request_fingerprint' => $fingerprint,
                    ])->save();

                    if ($method === 'bank_transfer') {
                        $this->checkouts->selectBankTransfer($checkout, (string) $normalizedMobile);
                    } else {
                        $this->checkouts->selectQrPh($checkout);
                    }

                    return ['checkout' => $checkout->refresh(), 'replayed' => false];
                });
        } catch (LockTimeoutException) {
            abort(429, 'Another funded order request is in progress for this client. Retry with the same idempotency key.');
        }
    }
}
