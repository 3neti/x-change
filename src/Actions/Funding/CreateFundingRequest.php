<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferWindow;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingTransferAmountReservation;
use LBHurtado\XChange\Services\Funding\FundingRequestWorkflowPublisher;
use RuntimeException;

final class CreateFundingRequest
{
    public function __construct(
        private readonly FundingAccountCreditContract $accounts,
        private readonly IssueTreasuryBackedPayCode $payCodes,
        private readonly ReserveFundingTransferAmount $reserveTransferAmount,
        private readonly FundingRequestWorkflowPublisher $workflow,
    ) {}

    public function handle(CreateFundingRequestData $data): FundingRequest
    {
        if ($data->requestedValueMinor <= 0) {
            throw new InvalidArgumentException('Requested value must be greater than zero.');
        }

        $currency = mb_strtoupper(trim($data->currency));
        $transferWindow = $data->fundingType === FundingRequestType::BankTransfer
            ? ($data->transferWindow ?? FundingTransferWindow::Recent)
            : null;
        $idempotencyHash = hash('sha256', implode("\0", [
            $data->requesterType,
            $data->requesterId,
            trim($data->idempotencyKey),
        ]));
        $fingerprint = hash('sha256', json_encode([
            'account_reference' => trim($data->accountReference),
            'funding_type' => $data->fundingType->value,
            'requested_value_minor' => $data->requestedValueMinor,
            'currency' => $currency,
            'transfer_window' => $transferWindow?->value,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use (
                $data,
                $currency,
                $idempotencyHash,
                $fingerprint,
                $transferWindow,
            ): FundingRequest {
                $request = FundingRequest::query()->create([
                    'account_reference' => trim($data->accountReference),
                    'requester_type' => $data->requesterType,
                    'requester_id' => $data->requesterId,
                    'funding_type' => $data->fundingType,
                    'requested_value_minor' => $data->requestedValueMinor,
                    'currency' => $currency,
                    'status' => FundingRequestStatus::Submitted,
                    'version' => 1,
                    'idempotency_key_hash' => $idempotencyHash,
                    'idempotency_fingerprint' => $fingerprint,
                    'description' => trim($data->description),
                    'external_reference_ciphertext' => $data->externalReference,
                    'occurred_on' => $data->occurredOn,
                    'requester_notes_ciphertext' => $data->requesterNotes,
                    'submitted_at' => now(),
                    'metadata' => [
                        'attachments_enabled' => false,
                        'monetary_authority' => 'independent_backing_verification_only',
                        'transfer_window' => $transferWindow?->value,
                    ],
                ]);

                $request->events()->create([
                    'sequence' => 1,
                    'event_type' => 'submitted',
                    'from_status' => null,
                    'to_status' => FundingRequestStatus::Submitted,
                    'actor_type' => $data->requesterType,
                    'actor_id' => $data->requesterId,
                    'metadata' => ['attachments_received' => false],
                    'occurred_at' => now(),
                ]);

                $requester = data_get(
                    $this->accounts->resolve($request->account_reference),
                    'holder',
                );

                if (
                    ! $requester instanceof Model
                    || ! $requester instanceof Authenticatable
                    || $requester::class !== $request->requester_type
                    || (string) $requester->getKey() !== $request->requester_id
                ) {
                    throw new RuntimeException(
                        'Funding Request principal binding is invalid.',
                    );
                }

                $collectionWalletId = $requester->wallet?->id;

                if (! $collectionWalletId) {
                    throw new RuntimeException(
                        'Funding Request Pay Code issuance requires an explicit collection wallet.',
                    );
                }

                $isProviderVerifiableTransfer = $data->fundingType
                    === FundingRequestType::BankTransfer
                    && (bool) config(
                        'x-change.funding.requests.bank_transfer.enabled',
                        true,
                    );
                $transferAmountReservation = $isProviderVerifiableTransfer
                    && (bool) config(
                        'x-change.funding.requests.bank_transfer.reserved_amounts.enabled',
                        true,
                    )
                    ? $this->reserveTransferAmount->handle($request)
                    : null;
                $collectionAmountMinor = $transferAmountReservation
                    ?->expected_amount_minor ?? $data->requestedValueMinor;
                $expiresAt = now()->addSeconds((int) config(
                    'x-change.funding.requests.code_ttl_seconds',
                    604800,
                ));
                $voucher = $this->payCodes->handle(
                    issuer: $requester,
                    instructions: [
                        'cash' => [
                            'amount' => 0,
                            'currency' => $currency,
                            'validation' => ['country' => 'PH'],
                        ],
                        'inputs' => ['fields' => []],
                        'feedback' => [],
                        'rider' => [],
                        'count' => 1,
                        'prefix' => 'FUND',
                        'mask' => '****',
                        'expires_at' => $expiresAt,
                        'voucher_type' => VoucherType::PAYABLE->value,
                        'target_amount' => $collectionAmountMinor / 100,
                        'rules' => [
                            'min_payment' => $collectionAmountMinor / 100,
                            'max_payment' => $collectionAmountMinor / 100,
                            'allow_overpayment' => false,
                            'auto_close_on_full_payment' => true,
                            'allowed_payer' => 'system_principal',
                        ],
                        'execution' => [
                            'driver' => $isProviderVerifiableTransfer
                                ? 'x_change_provider_funding'
                                : 'x_change_account_funding',
                            'mode' => 'collection',
                            'metadata' => [
                                'funding_request_reference' => $request->reference,
                                'requested_amount_minor' => $data->requestedValueMinor,
                                'expected_amount_minor' => $collectionAmountMinor,
                            ],
                        ],
                        'metadata' => [
                            'flow_type' => 'collectible',
                            'issuer_id' => (string) $requester->getAuthIdentifier(),
                            'collection_wallet_id' => $collectionWalletId,
                            'settlement_driver' => (string) config(
                                'x-change.funding.requests.envelope_driver',
                                'account-funding-review',
                            ),
                            'requires_envelope' => true,
                            'custom' => [
                                'account_funding_request' => [
                                    'reference' => $request->reference,
                                    'account_reference' => $request->account_reference,
                                    'requested_amount_minor' => $data->requestedValueMinor,
                                    'expected_amount_minor' => $collectionAmountMinor,
                                ],
                            ],
                        ],
                    ],
                    expiresAt: $expiresAt,
                    initialState: VoucherState::LOCKED,
                );
                $envelope = $voucher->createEnvelope(
                    driverId: (string) config(
                        'x-change.funding.requests.envelope_driver',
                        'account-funding-review',
                    ),
                    initialPayload: array_filter([
                        'request_reference' => $request->reference,
                        'account_reference' => $request->account_reference,
                        'funding_type' => $request->funding_type->value,
                        'requested_value_minor' => $request->requested_value_minor,
                        'matching_adjustment_minor' => $transferAmountReservation
                            ?->matching_adjustment_minor,
                        'expected_value_minor' => $collectionAmountMinor,
                        'currency' => $request->currency,
                        'description' => $request->description,
                        'external_reference' => $data->externalReference,
                        'occurred_on' => $data->occurredOn?->format('Y-m-d'),
                    ], static fn (mixed $value): bool => $value !== null),
                    context: [
                        'funding_request_reference' => $request->reference,
                        'sensitive_evidence' => true,
                        'monetary_authority' => 'independent_backing_verification_only',
                    ],
                    actor: $requester,
                );
                $request->forceFill([
                    'voucher_id' => $voucher->getKey(),
                    'metadata' => [
                        'attachments_enabled' => (bool) config(
                            'x-change.funding.requests.attachments_enabled',
                            true,
                        ),
                        'monetary_authority' => 'independent_backing_verification_only',
                        'settlement_envelope_id' => $envelope->getKey(),
                        'provider_verification_enabled' => $isProviderVerifiableTransfer,
                        'transfer_amount_reservation_id' => $transferAmountReservation
                            ?->getKey(),
                        'requested_amount_minor' => $data->requestedValueMinor,
                        'matching_adjustment_minor' => $transferAmountReservation
                            ?->matching_adjustment_minor,
                        'expected_amount_minor' => $collectionAmountMinor,
                        'full_expected_amount_is_credited' => $transferAmountReservation
                            instanceof FundingTransferAmountReservation,
                        'transfer_window' => $transferWindow?->value,
                        'sender_reference_authority' => false,
                    ],
                ])->saveQuietly();
                $this->workflow->submitted($request->refresh());

                return $request->refresh()->load([
                    'events',
                    'transferAmountReservation',
                    'voucher.envelope',
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = FundingRequest::query()
                ->where('idempotency_key_hash', $idempotencyHash)
                ->first();

            if (! $existing instanceof FundingRequest) {
                throw $exception;
            }

            if (! hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
                throw new RuntimeException(
                    'The Funding Request idempotency key was already used for different instructions.',
                );
            }

            return $existing->load(['events', 'transferAmountReservation']);
        }
    }
}
