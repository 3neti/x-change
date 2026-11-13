<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Exceptions\ExternalReferenceConflict;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Accounts\AccountLifecycle;
use LBHurtado\XChange\Services\IdempotencyService;
use RuntimeException;

final readonly class PartnerIssuanceReservationService
{
    public function __construct(
        private IdempotencyService $idempotency,
        private PartnerApiMandateGuard $mandates,
        private PartnerPayCodeReferenceService $references,
    ) {}

    /**
     * Reserve a reference before any external funding instruction is created.
     *
     * @param  array<string, mixed>  $instructions
     * @return array{id: int, replayed: bool}
     */
    public function reserve(PartnerApiClient $client, string $externalReference, string $key, array $instructions, int $principalMinor, string $currency): array
    {
        if ($principalMinor < 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1
            || preg_match('/^[A-Za-z0-9_.:-]{1,190}$/D', $externalReference) !== 1
            || trim($key) === '' || mb_strlen($key) > 160 || $key !== trim($key)) {
            throw ValidationException::withMessages(['reservation' => ['The issuance reservation is invalid.']]);
        }

        $requestHash = $this->idempotency->fingerprint([
            'instructions' => $instructions,
            'principal_minor' => $principalMinor,
            'currency' => $currency,
        ]);
        $keyHash = hash('sha256', $key);
        $instructionsHash = $this->instructionHash($instructions);

        return DB::transaction(function () use ($client, $externalReference, $keyHash, $requestHash, $instructionsHash, $instructions, $principalMinor, $currency): array {
            $client = PartnerApiClient::query()->lockForUpdate()->findOrFail($client->getKey());
            abort_unless($client->isActive(), 401);

            $byKey = DB::table('x_change_partner_issuance_reservations')
                ->where('partner_api_client_id', $client->getKey())->where('idempotency_key_hash', $keyHash)->first();
            if ($byKey !== null) {
                if ($byKey->external_reference !== $externalReference || ! hash_equals($byKey->request_hash, $requestHash)) {
                    throw new IdempotencyConflict('The idempotency key is already bound to different instructions.');
                }

                return ['id' => (int) $byKey->id, 'replayed' => true];
            }

            $issuer = $client->issuer;
            abort_unless($issuer !== null && app(AccountLifecycle::class)->lockAndIsActive($issuer), 403, 'The account is suspended.');

            if (DB::table('x_change_partner_issuance_reservations')->where('partner_api_client_id', $client->getKey())
                ->where('external_reference', $externalReference)->exists()
                || PartnerApiPayCodeReference::query()->whereBelongsTo($client, 'client')
                    ->where('external_reference', $externalReference)->exists()) {
                throw new ExternalReferenceConflict('The external reference is already reserved or issued.');
            }

            $maximumPending = max(1, (int) config('x-change.partner_api.funded_orders.maximum_open_per_client', 3));
            $pending = DB::table('x_change_partner_issuance_reservations')->where('partner_api_client_id', $client->getKey())
                ->where('status', 'pending')->count();
            abort_if($pending >= $maximumPending, 429, 'The client has reached its limit of outstanding account-funded orders.');

            $context = new PartnerApiRequestContext;
            $context->setClient($client);
            $this->mandates->assertAllows($instructions, $context);
            if ($this->mandates->principalMinor($instructions) !== $principalMinor
                || strtoupper((string) data_get($instructions, 'cash.currency')) !== $currency) {
                throw ValidationException::withMessages(['reservation' => ['The reserved principal does not match the validated instructions.']]);
            }
            $this->mandates->assertDailyPrincipalAvailable($principalMinor, $context);

            $id = DB::table('x_change_partner_issuance_reservations')->insertGetId([
                'partner_api_client_id' => $client->getKey(), 'external_reference' => $externalReference,
                'idempotency_key_hash' => $keyHash, 'request_hash' => $requestHash,
                'instructions_hash' => $instructionsHash,
                'principal_minor' => $principalMinor, 'currency' => $currency,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => (int) $id, 'replayed' => false];
        }, attempts: 5);
    }

    public function complete(int $reservationId, PayCodeIssuanceFundingOrder $order, GeneratePayCodeResultData $result): void
    {
        DB::transaction(function () use ($reservationId, $order, $result): void {
            [$client, $reservation] = $this->lockReservation($reservationId);
            $issuer = $client->issuer;
            $voucher = Voucher::query()->findOrFail($result->voucher_id);
            if ($issuer === null || $order->issuer_type !== $issuer::class || (string) $order->issuer_id !== (string) $issuer->getKey()
                || $voucher->owner_type !== $issuer->getMorphClass() || (string) $voucher->owner_id !== (string) $issuer->getKey()
                || (int) data_get($order->instructions_ciphertext, '_meta.partner_issuance_reservation_id') !== $reservationId
                || ! hash_equals($reservation->instructions_hash, $this->instructionHash($order->instructions_ciphertext))
                || $this->references->externalReference($voucher) !== $reservation->external_reference
                || ($reservation->funding_order_id !== null && (int) $reservation->funding_order_id !== (int) $order->getKey())) {
                throw new RuntimeException('The Partner issuance reservation does not match the order and Pay Code.');
            }
            if ($reservation->status === 'completed') {
                if ((int) $reservation->voucher_id !== (int) $voucher->getKey()) {
                    throw new RuntimeException('The Partner issuance reservation belongs to another Pay Code.');
                }

                return;
            }
            if ($reservation->status !== 'pending') {
                throw new RuntimeException('The Partner issuance reservation cannot be completed.');
            }

            PartnerApiPayCodeReference::query()->create([
                'partner_api_client_id' => $client->getKey(), 'external_reference' => $reservation->external_reference,
                'voucher_id' => $voucher->getKey(), 'terms_hash' => $this->references->termsHash($order->instructions_ciphertext),
            ]);
            PartnerApiOperation::query()->create([
                'partner_api_client_id' => $client->getKey(), 'operation' => 'pay_code_issued',
                'idempotency_key' => 'funded-reservation:'.$reservationId, 'subject_reference' => $voucher->code,
                'principal_minor' => $reservation->principal_minor, 'currency' => $reservation->currency,
                'response_snapshot' => $result->toArray(), 'occurred_at' => now(),
            ]);
            DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->update([
                'funding_order_id' => $order->getKey(), 'voucher_id' => $voucher->getKey(), 'status' => 'completed', 'updated_at' => now(),
            ]);
        }, attempts: 5);
    }

    public function bindToOrder(int $reservationId, PayCodeIssuanceFundingOrder $order): void
    {
        DB::transaction(function () use ($reservationId, $order): void {
            [$client, $reservation] = $this->lockReservation($reservationId);
            $issuer = $client->issuer;
            if ($issuer === null || $order->issuer_type !== $issuer::class || (string) $order->issuer_id !== (string) $issuer->getKey()
                || (int) data_get($order->instructions_ciphertext, '_meta.partner_issuance_reservation_id') !== $reservationId
                || ! hash_equals($reservation->instructions_hash, $this->instructionHash($order->instructions_ciphertext))
                || $reservation->status !== 'pending'
                || ($reservation->funding_order_id !== null && (int) $reservation->funding_order_id !== (int) $order->getKey())) {
                throw new RuntimeException('The Partner issuance reservation does not match the funding order.');
            }
            if ($reservation->funding_order_id === null) {
                DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->update([
                    'funding_order_id' => $order->getKey(), 'updated_at' => now(),
                ]);
            }
        }, attempts: 5);
    }

    /** @return array{PartnerApiClient, object} */
    private function lockReservation(int $reservationId): array
    {
        $clientId = DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->value('partner_api_client_id');
        if ($clientId === null) {
            throw new RuntimeException('The Partner issuance reservation is unavailable.');
        }

        $client = PartnerApiClient::query()->lockForUpdate()->findOrFail($clientId);
        $reservation = DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->lockForUpdate()->first();
        if ($reservation === null || (string) $reservation->partner_api_client_id !== (string) $client->getKey()) {
            throw new RuntimeException('The Partner issuance reservation is unavailable.');
        }

        return [$client, $reservation];
    }

    /** @param array<string, mixed> $instructions */
    private function instructionHash(array $instructions): string
    {
        data_forget($instructions, '_meta.partner_issuance_reservation_id');

        return $this->idempotency->fingerprint($instructions);
    }
}
