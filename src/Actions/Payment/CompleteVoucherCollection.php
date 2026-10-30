<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Exceptions\VoucherCollectionConflict;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Execution\VoucherValueFlowPolicy;
use LBHurtado\XChange\Services\Payment\PartnerPaymentEventOutbox;
use LBHurtado\XChange\Services\Payment\VoucherCollectionJournal;
use LBHurtado\XChange\Services\Payment\VoucherCollectionPostingRegistry;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\SettlementRepaymentPolicy;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use RuntimeException;

final readonly class CompleteVoucherCollection
{
    public function __construct(
        private VoucherCapabilityGuard $guard,
        private SettlementCollectionGate $settlementCollectionGate,
        private VoucherCollectionProgressService $progress,
        private VoucherCollectionPostingRegistry $postings,
        private VoucherCollectionJournal $journal,
        private VoucherValueFlowPolicy $valueFlows,
    ) {}

    public function handle(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $data,
    ): VoucherCollection {
        if ($data->amountMinor <= 0) {
            throw new RuntimeException('Confirmed collection amount must be positive.');
        }

        return DB::transaction(function () use ($voucher, $data): VoucherCollection {
            $locked = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());
            $existing = VoucherCollection::query()
                ->where('voucher_id', $locked->getKey())
                ->where('idempotency_key', $data->idempotencyKey)
                ->first();

            if ($existing instanceof VoucherCollection) {
                if (! hash_equals(
                    (string) $existing->idempotency_fingerprint,
                    $data->fingerprint(),
                )) {
                    throw VoucherCollectionConflict::forIdempotencyKey(
                        $data->idempotencyKey,
                    );
                }

                return $existing;
            }

            $this->guard->ensureCanCollect($locked);
            $usesTypedCollectionLifecycle = in_array(
                $locked->voucher_type,
                [VoucherType::PAYABLE, VoucherType::SETTLEMENT],
                true,
            );

            if (
                $usesTypedCollectionLifecycle
                && ! $locked->canAcceptPayment()
            ) {
                throw new RuntimeException(
                    'The Pay Code is not active for collection.',
                );
            }

            $this->settlementCollectionGate->ensureCollectibleSettlementIsReady(
                voucher: $locked,
            );

            if ($data->executionDriver !== 'provider_wallet'
                && ($this->valueFlows->source($locked) !== null
                    || $this->valueFlows->destination($locked) !== null)) {
                throw new RuntimeException(
                    'This collection execution driver does not support explicit value flow addresses.',
                );
            }

            $progress = $this->progress->compute($locked);
            $currency = mb_strtoupper(trim($data->currency));
            $rules = (array) data_get(
                $locked->metadata,
                'instructions.rules',
                $locked->instructions?->rules ?? [],
            );

            if ($currency !== mb_strtoupper($progress->currency)) {
                throw new RuntimeException(
                    'Confirmed collection currency does not match the Pay Code.',
                );
            }

            $feeMinor = app(PayCodeCommercialSaleService::class)
                ->outstandingCollectionFee($locked);
            $principalMinor = $data->amountMinor - $feeMinor;
            if ($principalMinor <= 0 || ($feeMinor > 0 && $data->executionDriver !== 'provider_wallet')) {
                throw new RuntimeException('Payment must cover the service fee and a positive principal through the provider.');
            }

            if (SettlementRepaymentPolicy::forVoucher($locked)
                && $principalMinor !== SettlementRepaymentPolicy::paymentMinor($locked)) {
                throw new RuntimeException('The repayment must match the fixed installment amount.');
            }

            $allowOverpayment = (bool) Arr::get(
                $rules,
                'allow_overpayment',
                false,
            );

            if (
                $usesTypedCollectionLifecycle
                && ! $allowOverpayment
                && $principalMinor > $progress->remaining_to_collect_minor
            ) {
                throw new RuntimeException(
                    'Confirmed collection exceeds the remaining Pay Code target.',
                );
            }

            $posting = $this->postings
                ->resolve($data->executionDriver)
                ->post($locked, $data);
            $collectionNumber = ((int) VoucherCollection::query()
                ->where('voucher_id', $locked->getKey())
                ->max('collection_number')) + 1;
            $collection = VoucherCollection::query()->create([
                'voucher_id' => $locked->getKey(),
                'collection_number' => $collectionNumber,
                'status' => 'collected',
                'requested_amount_minor' => $data->amountMinor,
                'collected_amount_minor' => $principalMinor,
                'currency' => $currency,
                'provider' => $data->provider,
                'provider_reference' => $data->providerReference,
                'provider_transaction_id' => $data->providerTransactionId,
                'payer_mobile' => $data->payerMobile,
                'payer_name' => $data->payerName,
                'wallet_transaction_id' => $posting->walletTransactionId,
                'idempotency_key' => $data->idempotencyKey,
                'idempotency_fingerprint' => $data->fingerprint(),
                'execution_driver' => $data->executionDriver,
                'treasury_operation_reference' => $posting->treasuryOperationReference,
                'attempted_at' => now(),
                'completed_at' => now(),
                'meta' => [
                    'gross_amount_minor' => $data->amountMinor,
                    'fee_amount_minor' => $feeMinor,
                    'principal_amount_minor' => $principalMinor,
                    'payload' => data_get(
                        $data->metadata,
                        'confirmed_payload',
                        [],
                    ),
                    'result' => [
                        'status' => 'collected',
                        'amount' => $principalMinor / 100,
                        'currency' => $currency,
                        'provider' => $data->provider,
                        'provider_reference' => $data->providerReference,
                        'provider_transaction_id' => $data->providerTransactionId,
                    ],
                    'authority' => [
                        'type' => $data->authority,
                        'reference' => $data->authorityReference,
                    ],
                    'posting' => $posting->metadata,
                    'collection' => $data->metadata,
                ],
            ]);
            app(PartnerPaymentEventOutbox::class)->record($collection);
            DB::afterCommit(
                fn () => $this->journal->record($collection->fresh()),
            );
            $updatedProgress = $this->progress->persistSummary($locked);

            if (
                $updatedProgress->is_fully_collected
                && (bool) Arr::get(
                    $rules,
                    'auto_close_on_full_payment',
                    false,
                )
            ) {
                $locked->forceFill([
                    'state' => VoucherState::CLOSED,
                ])->saveQuietly();
            }

            return $collection;
        }, attempts: 5);
    }
}
