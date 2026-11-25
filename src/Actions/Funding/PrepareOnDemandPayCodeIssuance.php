<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldPlacementData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Exceptions\FundingIntentConflict;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Accounts\AccountLifecycle;
use LBHurtado\XChange\Services\Banking\SelectedAccountClientFunds;
use LBHurtado\XChange\Services\Commercial\CommercialCustomerChargingGuard;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;
use LBHurtado\XChange\Services\PartnerApi\PartnerIssuanceReservationService;
use RuntimeException;
use Throwable;

final readonly class PrepareOnDemandPayCodeIssuance
{
    public function __construct(
        private WalletAccessContract $wallets,
        private OnDemandIssuanceFundingRequirement $requirements,
        private FundingDestinationResolverContract $destinations,
        private CreateFundingIntent $createFundingIntent,
        private IssueFundingInstructions $issueFundingInstructions,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryHoldOperationContract $holds,
        private TransitionPayCodeIssuanceFundingOrder $transition,
        private ReserveOnDemandIssuanceAmountLease $amountLeases,
        private CommercialCustomerChargingGuard $customerCharging,
        private PartnerIssuanceReservationService $partnerReservations,
    ) {}

    /**
     * @param  array<string, mixed>  $instructions
     */
    public function handle(
        Model $issuer,
        array $instructions,
        PricingEstimateData $pricing,
        string $idempotencyKey,
        ?OnDemandIssuanceFundingBasis $fundingBasis = null,
        bool $requirePayerIdentityMatch = false,
    ): PayCodeIssuanceFundingOrder {
        $instructionsFingerprint = $this->fingerprint($instructions);
        $pricingSnapshot = $pricing->toArray();
        $pricingFingerprint = $this->fingerprint($pricingSnapshot);
        $idempotencyKeyHash = hash('sha256', implode("\0", [
            $issuer::class,
            (string) $issuer->getKey(),
            trim($idempotencyKey),
        ]));
        $existing = PayCodeIssuanceFundingOrder::query()
            ->where('idempotency_key_hash', $idempotencyKeyHash)
            ->first();

        if ($existing instanceof PayCodeIssuanceFundingOrder) {
            $basis = $fundingBasis ?? $existing->funding_basis;
            $requiredAmountMinor = $existing->required_amount_minor;
            $idempotencyFingerprint = hash('sha256', implode('|', [
                $instructionsFingerprint,
                $pricingFingerprint,
                $basis->value,
                (string) $requiredAmountMinor,
            ]));
            if (! hash_equals($existing->idempotency_fingerprint, $idempotencyFingerprint)) {
                throw FundingIntentConflict::idempotency();
            }

            return $existing->load(['fundingIntent', 'events']);
        }

        $selectedAccountFunds = app(SelectedAccountClientFunds::class)->forOwner($issuer);
        if ($selectedAccountFunds['mode'] === 'unavailable'
            || ($selectedAccountFunds['mode'] === 'dedicated'
                && ! (bool) config('x-change.issuance_funding.on_demand.assigned_netbank_enabled', false))) {
            throw new RuntimeException('On-demand funding for the selected bank account is not yet available.');
        }
        $selectedBalance = $selectedAccountFunds['mode'] === 'dedicated'
            ? $selectedAccountFunds['amount_minor'] : null;
        $requirement = $fundingBasis === null
            ? $this->requirements->for($issuer, $pricing, $selectedBalance)
            : $this->requirements->forBasis($issuer, $pricing, $fundingBasis, $selectedBalance);
        $requiredAmountMinor = $requirement->requiredAmountMinor;
        if ($requiredAmountMinor === 0) {
            throw new RuntimeException('A funding order requires a positive authoritative amount.');
        }

        $provider = mb_strtolower((string) config(
            'x-change.funding.requests.bank_transfer.provider',
            'netbank',
        ));
        $connectionReference = (string) config(
            'x-change.funding.requests.bank_transfer.connection_reference',
            'netbank-primary',
        );
        $currency = mb_strtoupper($pricing->currency);
        $wallet = $this->wallets->resolveForUser($issuer);
        $accountReference = $this->accountReference($wallet);
        $basis = $requirement->basis;
        $reservedClientFundsMinor = $requirement->reservedClientFundsMinor;
        $onDemandAmountMinor = $requirement->externalAmountMinor;
        $idempotencyFingerprint = hash('sha256', implode('|', [
            $instructionsFingerprint,
            $pricingFingerprint,
            $basis->value,
            (string) $requiredAmountMinor,
        ]));
        $expiresAt = now()->addSeconds((int) config(
            'x-change.issuance_funding.on_demand.ttl_seconds',
            1800,
        ));

        $destination = null;
        if ($selectedAccountFunds['mode'] === 'dedicated') {
            if ($provider !== 'netbank' || $onDemandAmountMinor <= 0) {
                throw new RuntimeException('The selected account cannot use this on-demand funding path.');
            }
            $destination = $this->destinations->resolve($issuer, $provider, $accountReference);
            $linkId = $selectedAccountFunds['account_link_id'];
            $connectionReference = $selectedAccountFunds['connection_reference'];
            if ($destination->mode !== 'dedicated'
                || data_get($destination->metadata, 'assigned_bank_account_link_id') !== $linkId
                || data_get($destination->metadata, 'treasury_connection_reference') !== $connectionReference
                || data_get($destination->metadata, 'netbank_instruction_mode') !== 'direct_qr') {
                throw new RuntimeException('The selected account funding destination changed or is unavailable.');
            }
            app(BankingAccountSelectorContract::class)->select($issuer, $provider, 'disbursement', $linkId);
            $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $issuer)
                ->where('provider_account_link_id', $linkId)->first();
            $customerId = data_get($assignment?->details_ciphertext, 'customer_id');
            if (! is_string($customerId) || $customerId === '') {
                throw new RuntimeException('The selected account customer ID is unavailable.');
            }
            $destination->metadata['on_demand_customer_id'] = $customerId;
            $destination->metadata['require_exact_vca'] = true;
        }

        abort_unless(app(AccountLifecycle::class)->isActive($issuer), 403, 'The account is suspended.');

        $this->customerCharging->ensureAuthorized($pricing->customer_charge_minor);

        $order = DB::transaction(function () use (
            $issuer,
            $instructions,
            $instructionsFingerprint,
            $pricingSnapshot,
            $pricingFingerprint,
            $accountReference,
            $provider,
            $connectionReference,
            $basis,
            $requiredAmountMinor,
            $reservedClientFundsMinor,
            $onDemandAmountMinor,
            $currency,
            $idempotencyKeyHash,
            $idempotencyFingerprint,
            $expiresAt,
            $destination,
            $selectedAccountFunds,
        ): PayCodeIssuanceFundingOrder {
            if ($destination !== null) {
                $preference = FundingDestinationPreference::query()->whereMorphedTo('owner', $issuer)
                    ->where('provider_code', 'netbank')->lockForUpdate()->first();
                if ($preference?->mode !== 'dedicated'
                    || (int) $preference->provider_account_link_id !== $selectedAccountFunds['account_link_id']) {
                    throw new RuntimeException('The selected account changed while preparing the order.');
                }
            }
            abort_unless(app(AccountLifecycle::class)->lockAndIsActive($issuer), 403, 'The account is suspended.');
            $order = PayCodeIssuanceFundingOrder::query()->create([
                'account_reference' => $accountReference,
                'issuer_type' => $issuer::class,
                'issuer_id' => (string) $issuer->getKey(),
                'provider' => $provider,
                'connection_reference' => $connectionReference,
                'funding_basis' => $basis,
                'instructions_ciphertext' => $instructions,
                'instructions_fingerprint' => $instructionsFingerprint,
                'pricing_snapshot_ciphertext' => $pricingSnapshot,
                'pricing_fingerprint' => $pricingFingerprint,
                'required_amount_minor' => $requiredAmountMinor,
                'reserved_client_funds_minor' => $reservedClientFundsMinor,
                'on_demand_amount_minor' => $onDemandAmountMinor,
                'reconciliation_adjustment_minor' => 0,
                'expected_payment_minor' => $onDemandAmountMinor,
                'currency' => $currency,
                'status' => PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                'version' => 1,
                'idempotency_key_hash' => $idempotencyKeyHash,
                'idempotency_fingerprint' => $idempotencyFingerprint,
                'treasury_hold_reference' => $reservedClientFundsMinor > 0
                    ? 'issuance-hold:'.Str::lower((string) Str::ulid())
                    : null,
                'expires_at' => $expiresAt,
                'metadata' => [
                    'source' => data_get($instructions, '_meta.source', 'cockpit.quick-generate'),
                    'bank_transfer_primary' => $destination === null,
                    ...($destination === null ? [] : ['funding_source' => [
                        'mode' => 'dedicated',
                        'account_link_id' => $selectedAccountFunds['account_link_id'],
                        'connection_reference' => $connectionReference,
                        'destination_fingerprint' => $destination->fingerprint,
                    ]]),
                ],
            ]);
            $reservationId = data_get($instructions, '_meta.partner_issuance_reservation_id');
            if ($reservationId !== null) {
                $this->partnerReservations->bindToOrder((int) $reservationId, $order);
            }
            $order->events()->create([
                'sequence' => 1,
                'event_type' => 'prepared',
                'from_status' => null,
                'to_status' => PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                'actor_type' => $issuer::class,
                'actor_id' => (string) $issuer->getKey(),
                'metadata' => [
                    'funding_basis' => $basis->value,
                    'required_amount_minor' => $requiredAmountMinor,
                    'reserved_client_funds_minor' => $reservedClientFundsMinor,
                ],
                'occurred_at' => now(),
            ]);

            if ($reservedClientFundsMinor > 0) {
                $this->placeClientFundsHold($issuer, $order, $reservedClientFundsMinor);
            }

            return $order;
        }, attempts: 5);

        if ($onDemandAmountMinor === 0) {
            $order = $this->transition->handle(
                order: $order,
                status: PayCodeIssuanceFundingOrderStatus::Funded,
                eventType: 'client_funds_held',
                actorType: self::class,
                actorId: $order->reference,
                attributes: ['funded_at' => now()],
            );
            DB::afterCommit(static function () use ($order): void {
                ResumeOnDemandPayCodeIssuanceJob::dispatch($order->getKey());
            });

            return $order;
        }

        try {
            $order = $this->amountLeases->handle($order);
            $intent = $this->createFundingIntent->handle(new CreateFundingIntentData(
                accountReference: $accountReference,
                provider: $provider,
                expectedAmountMinor: $order->expected_payment_minor,
                currency: $currency,
                idempotencyKey: 'issuance-funding:'.$order->reference,
                actorType: $issuer::class,
                actorId: (string) $issuer->getKey(),
                expiresAt: new DateTimeImmutable($expiresAt->toIso8601String()),
                metadata: [
                    'funding_order_reference' => $order->reference,
                    'connection_reference' => $connectionReference,
                    'payer_identity_match_required' => $requirePayerIdentityMatch,
                ],
                destination: $destination ?? $this->destinations->resolve($issuer, $provider, $accountReference),
                purpose: FundingIntentPurpose::OnDemandIssuance,
            ));
            $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
            $this->issueFundingInstructions->handle(
                $intent,
                $issuer::class,
                (string) $issuer->getKey(),
            );
        } catch (Throwable $exception) {
            $this->transition->handle(
                order: $order,
                status: PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
                eventType: 'funding_instructions_failed',
                actorType: self::class,
                actorId: $order->reference,
                metadata: ['failure_type' => class_basename($exception)],
            );

            throw $exception;
        }

        return $order->refresh()->load(['fundingIntent', 'events']);
    }

    private function placeClientFundsHold(
        Model $issuer,
        PayCodeIssuanceFundingOrder $order,
        int $amountMinor,
    ): void {
        $positions = $this->portfolios->provision(
            $issuer,
            [$order->connection_reference],
        )->positions;
        $clientFunds = collect($positions)->first(
            static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds,
        );
        $payCodeReserve = collect($positions)->first(
            static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::PayCodeReserve,
        );

        if ($clientFunds === null || $payCodeReserve === null || $order->treasury_hold_reference === null) {
            throw new RuntimeException('The Treasury positions required for the issuance hold are unavailable.');
        }

        $this->holds->place(new TreasuryHoldPlacementData(
            operationReference: 'issuance-hold-place:'.$order->reference,
            holdReference: $order->treasury_hold_reference,
            sourcePositionReference: $clientFunds->positionReference,
            heldPositionReference: $payCodeReserve->positionReference,
            amountMinor: $amountMinor,
            currency: $order->currency,
            idempotencyKey: 'issuance-hold-place-key:'.$order->reference,
            externalReference: 'issuance-funding-order:'.$order->reference,
            maximumAmountMinor: $order->required_amount_minor,
            replenishable: $order->on_demand_amount_minor > 0,
            metadata: ['funding_order_reference' => $order->reference],
        ));
    }

    private function accountReference(mixed $wallet): string
    {
        $uuid = data_get($wallet, 'uuid');

        if (is_string($uuid) && trim($uuid) !== '') {
            return 'wallet:'.trim($uuid);
        }

        if (is_object($wallet) && method_exists($wallet, 'getKey')) {
            return 'wallet:'.$wallet->getKey();
        }

        throw new RuntimeException('The issuance funding Account reference could not be resolved.');
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }
}
