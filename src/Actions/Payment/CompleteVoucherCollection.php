<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Exceptions\VoucherCollectionConflict;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Payment\VoucherCollectionPostingRegistry;
use LBHurtado\XChange\Services\SettlementCollectionGate;
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

            if (! $locked->canAcceptPayment()) {
                throw new RuntimeException(
                    'The Pay Code is not active for collection.',
                );
            }

            $this->settlementCollectionGate->ensureCollectibleSettlementIsReady(
                voucher: $locked,
                context: $this->settlementCollectionGate->contextFromVoucher($locked),
            );

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

            $allowOverpayment = (bool) Arr::get(
                $rules,
                'allow_overpayment',
                false,
            );

            if (
                ! $allowOverpayment
                && $data->amountMinor > $progress->remaining_to_collect_minor
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
                'collected_amount_minor' => $data->amountMinor,
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
                    'authority' => [
                        'type' => $data->authority,
                        'reference' => $data->authorityReference,
                    ],
                    'posting' => $posting->metadata,
                    'collection' => $data->metadata,
                ],
            ]);
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
