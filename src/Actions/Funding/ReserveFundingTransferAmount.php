<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferAmountReservationStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingTransferAmountReservation;
use RuntimeException;

final readonly class ReserveFundingTransferAmount
{
    public function handle(FundingRequest $fundingRequest): FundingTransferAmountReservation
    {
        if ($fundingRequest->funding_type !== FundingRequestType::BankTransfer) {
            throw new RuntimeException(
                'Reserved exact transfer amounts are only available for bank-transfer Funding Requests.',
            );
        }

        $provider = mb_strtolower(trim((string) config(
            'x-change.funding.requests.bank_transfer.provider',
            'netbank',
        )));
        $connectionReference = trim((string) config(
            'x-change.funding.requests.bank_transfer.connection_reference',
            'netbank-primary',
        ));
        $currency = mb_strtoupper($fundingRequest->currency);
        $scope = implode(':', [$provider, $connectionReference, $currency]);
        $lock = Cache::lock(
            'x-change:funding-transfer-amount:'.hash('sha256', $scope),
            max(5, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.lock_seconds',
                10,
            )),
        );

        return $lock->block(
            max(1, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.lock_wait_seconds',
                5,
            )),
            fn (): FundingTransferAmountReservation => $this->reserve(
                $fundingRequest,
                $provider,
                $connectionReference,
                $currency,
                $scope,
            ),
        );
    }

    private function reserve(
        FundingRequest $fundingRequest,
        string $provider,
        string $connectionReference,
        string $currency,
        string $scope,
    ): FundingTransferAmountReservation {
        return DB::transaction(function () use (
            $fundingRequest,
            $provider,
            $connectionReference,
            $currency,
            $scope,
        ): FundingTransferAmountReservation {
            $request = FundingRequest::query()
                ->lockForUpdate()
                ->findOrFail($fundingRequest->getKey());
            $existing = FundingTransferAmountReservation::query()
                ->where('funding_request_id', $request->getKey())
                ->first();

            if ($existing instanceof FundingTransferAmountReservation) {
                return $existing;
            }

            $now = CarbonImmutable::instance(now());
            $this->releaseReusableReservations(
                provider: $provider,
                connectionReference: $connectionReference,
                currency: $currency,
                now: $now,
            );
            $minimum = max(0, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.minimum_adjustment_minor',
                317,
            ));
            $maximum = max($minimum, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_adjustment_minor',
                537,
            ));
            $rangeSize = ($maximum - $minimum) + 1;
            $maxAttempts = min($rangeSize, max(1, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_allocation_attempts',
                $rangeSize,
            )));
            $start = random_int($minimum, $maximum);
            $ttlSeconds = max(60, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.ttl_seconds',
                600,
            ));
            $reuseDelaySeconds = max(0, (int) config(
                'x-change.funding.requests.bank_transfer.reserved_amounts.reuse_delay_seconds',
                3600,
            ));
            $expiresAt = $now->addSeconds($ttlSeconds);

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $adjustment = $minimum
                    + (($start - $minimum + $attempt) % $rangeSize);
                $expected = $request->requested_value_minor + $adjustment;

                if ($expected > 999_999_999_999_999) {
                    throw new RuntimeException(
                        'The requested value exceeds the supported reserved transfer amount.',
                    );
                }

                $activeKey = hash('sha256', implode("\0", [
                    $scope,
                    (string) $expected,
                ]));

                if (FundingTransferAmountReservation::query()
                    ->where('active_key', $activeKey)
                    ->exists()) {
                    continue;
                }

                try {
                    return FundingTransferAmountReservation::query()->create([
                        'funding_request_id' => $request->getKey(),
                        'provider_code' => $provider,
                        'connection_reference' => $connectionReference,
                        'currency' => $currency,
                        'requested_amount_minor' => $request->requested_value_minor,
                        'matching_adjustment_minor' => $adjustment,
                        'expected_amount_minor' => $expected,
                        'status' => FundingTransferAmountReservationStatus::Reserved,
                        'active_key' => $activeKey,
                        'reserved_at' => $now,
                        'expires_at' => $expiresAt,
                        'reusable_after' => $expiresAt->addSeconds($reuseDelaySeconds),
                        'metadata' => [
                            'allocation_strategy' => 'random_start_sequential_fallback',
                            'adjustment_range_minor' => [
                                'minimum' => $minimum,
                                'maximum' => $maximum,
                            ],
                            'full_expected_amount_is_credited' => true,
                            'adjustment_is_fee' => false,
                        ],
                    ]);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }
            }

            throw new RuntimeException(
                'No unique bank-transfer amount is currently available. Please try again after an existing instruction expires.',
            );
        }, 3);
    }

    private function releaseReusableReservations(
        string $provider,
        string $connectionReference,
        string $currency,
        CarbonImmutable $now,
    ): void {
        FundingTransferAmountReservation::query()
            ->where('provider_code', $provider)
            ->where('connection_reference', $connectionReference)
            ->where('currency', $currency)
            ->whereNotNull('active_key')
            ->where('reusable_after', '<=', $now)
            ->lockForUpdate()
            ->get()
            ->each(function (FundingTransferAmountReservation $reservation) use ($now): void {
                $status = $reservation->status
                    === FundingTransferAmountReservationStatus::Reserved
                    ? FundingTransferAmountReservationStatus::Expired
                    : $reservation->status;
                $reservation->forceFill([
                    'status' => $status,
                    'active_key' => null,
                    'released_at' => $now,
                ])->saveQuietly();
            });
    }
}
