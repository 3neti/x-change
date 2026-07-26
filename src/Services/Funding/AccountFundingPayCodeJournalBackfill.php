<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimOutcomeSelection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use RuntimeException;

final readonly class AccountFundingPayCodeJournalBackfill
{
    public function __construct(
        private AccountFundingPayCodeJournal $journal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(int $voucherId): array
    {
        $context = $this->validatedContext($voucherId);

        return $this->result(
            $context,
            $this->missingEvents($context),
            committed: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function backfill(int $voucherId): array
    {
        $missingBefore = [];

        DB::transaction(function () use (
            $voucherId,
            &$missingBefore,
        ): void {
            Voucher::query()
                ->whereKey($voucherId)
                ->lockForUpdate()
                ->firstOrFail();

            $context = $this->validatedContext($voucherId);
            $missingBefore = $this->missingEvents($context);

            foreach ($missingBefore as $eventType) {
                match ($eventType) {
                    'account_funding.pay_code.outcome_selected' => $this->journal->recordOutcomeSelected(
                        $context['selection'],
                    ),
                    'account_funding.pay_code.applied' => $this->journal->recordApplied($context['claim']),
                    default => throw new RuntimeException(
                        'An unsupported Account Funding journal event was requested.',
                    ),
                };
            }
        }, attempts: 5);

        $context = $this->validatedContext($voucherId);
        $missingAfter = $this->missingEvents($context);

        if ($missingAfter !== []) {
            throw new RuntimeException(
                'The Account Funding journal backfill did not complete.',
            );
        }

        return $this->result(
            $context,
            $missingBefore,
            committed: true,
        );
    }

    /**
     * @return array{
     *     voucher: Voucher,
     *     claim: VoucherClaim,
     *     selection: VoucherClaimOutcomeSelection,
     *     reservation: TreasuryPositionOperation,
     *     release: TreasuryPositionOperation
     * }
     */
    private function validatedContext(int $voucherId): array
    {
        if ($voucherId <= 0) {
            throw new RuntimeException(
                'A positive numeric Voucher ID is required.',
            );
        }

        $voucher = Voucher::query()->findOrFail($voucherId);
        $claims = VoucherClaim::query()
            ->where('voucher_id', $voucherId)
            ->where('settlement_mode', 'account_funding')
            ->get();

        if ($claims->count() !== 1) {
            throw new RuntimeException(
                'The Voucher must have exactly one Account Funding claim.',
            );
        }

        $claim = $claims->sole();
        $selections = VoucherClaimOutcomeSelection::query()
            ->where('voucher_id', $voucherId)
            ->get();

        if ($selections->count() !== 1) {
            throw new RuntimeException(
                'The Voucher must have exactly one claim outcome selection.',
            );
        }

        $selection = $selections->sole();
        $reservationReference = trim((string) data_get(
            $claim->meta,
            'reservation_operation_reference',
        ));
        $voucherReservationReference = trim((string) data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.operation_reference',
        ));
        $releaseReference = trim(
            (string) $claim->treasury_operation_reference,
        );

        if (
            $claim->status !== 'succeeded'
            || $claim->claim_type !== 'account_funding'
            || $claim->settlement_mode !== 'account_funding'
            || $claim->completed_at === null
            || $voucher->redeemed_at === null
        ) {
            throw new RuntimeException(
                'The Account Funding claim is not canonically complete.',
            );
        }

        if (
            $selection->outcome_key !== 'account_funding'
            || $selection->claimant_type !== $claim->claimant_type
            || (string) $selection->claimant_id !== (string) $claim->claimant_id
        ) {
            throw new RuntimeException(
                'The selected outcome does not match the Account Funding claim.',
            );
        }

        if (
            $reservationReference === ''
            || ! hash_equals(
                $voucherReservationReference,
                $reservationReference,
            )
            || $releaseReference === ''
            || ! hash_equals((string) $claim->reference, $releaseReference)
        ) {
            throw new RuntimeException(
                'The Account Funding Treasury references do not agree.',
            );
        }

        $reservation = TreasuryPositionOperation::query()
            ->with(['sourcePosition', 'destinationPosition'])
            ->where('operation_reference', $reservationReference)
            ->firstOrFail();
        $release = TreasuryPositionOperation::query()
            ->with(['sourcePosition', 'destinationPosition'])
            ->where('operation_reference', $releaseReference)
            ->firstOrFail();
        $amountMinor = (int) $claim->disbursed_amount_minor;
        $currency = mb_strtoupper(trim((string) $claim->currency));

        if (
            $amountMinor <= 0
            || (int) $claim->requested_amount_minor !== $amountMinor
            || (int) $claim->remaining_balance_minor !== 0
            || (int) $reservation->amount_minor !== $amountMinor
            || (int) $release->amount_minor !== $amountMinor
            || mb_strtoupper((string) $reservation->currency) !== $currency
            || mb_strtoupper((string) $release->currency) !== $currency
        ) {
            throw new RuntimeException(
                'The Account Funding claim and Treasury amounts do not agree.',
            );
        }

        if (
            $reservation->operation_type !== TreasuryPositionOperationType::Reservation
            || $release->operation_type !== TreasuryPositionOperationType::Release
            || $reservation->status !== 'committed'
            || $release->status !== 'committed'
            || $reservation->destination_position_id !== $release->source_position_id
            || ! hash_equals(
                (string) $release->external_reference,
                $reservationReference,
            )
        ) {
            throw new RuntimeException(
                'The Account Funding Treasury operations are not a committed reserve and release pair.',
            );
        }

        if (
            $reservation->destinationPosition?->purpose
                !== TreasuryPositionPurpose::PayCodeReserve
            || $release->sourcePosition?->purpose
                !== TreasuryPositionPurpose::PayCodeReserve
            || $release->destinationPosition?->purpose
                !== TreasuryPositionPurpose::ClientFunds
            || $release->destinationPosition?->principal_type
                !== $claim->claimant_type
            || (string) $release->destinationPosition?->principal_id
                !== (string) $claim->claimant_id
        ) {
            throw new RuntimeException(
                'The Account Funding Treasury positions do not match the claimant.',
            );
        }

        if (
            (int) data_get($reservation->metadata, 'pay_code_id') !== $voucherId
            || (int) data_get($release->metadata, 'pay_code_id') !== $voucherId
            || data_get($claim->meta, 'provider_calls') !== false
            || data_get($claim->meta, 'provider_inventory_changed') !== false
        ) {
            throw new RuntimeException(
                'The Account Funding evidence does not match a provider-free Pay Code transfer.',
            );
        }

        return [
            'voucher' => $voucher,
            'claim' => $claim,
            'selection' => $selection,
            'reservation' => $reservation,
            'release' => $release,
        ];
    }

    /**
     * @param  array{
     *     claim: VoucherClaim,
     *     selection: VoucherClaimOutcomeSelection
     * }  $context
     * @return list<string>
     */
    private function missingEvents(array $context): array
    {
        $expected = [
            'account_funding.pay_code.outcome_selected' => 'x-change:account-funding-pay-code:outcome-selected:'
                .$context['selection']->getKey(),
            'account_funding.pay_code.applied' => 'x-change:account-funding-pay-code:applied:'
                .$context['claim']->getKey(),
        ];
        $missing = [];

        foreach ($expected as $eventType => $idempotencyKey) {
            $existing = ExecutionJournalEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing instanceof ExecutionJournalEntry) {
                $missing[] = $eventType;

                continue;
            }

            if ($existing->event_type !== $eventType) {
                throw new RuntimeException(
                    'An Account Funding journal idempotency key belongs to a different event.',
                );
            }
        }

        return $missing;
    }

    /**
     * @param  array{
     *     voucher: Voucher,
     *     claim: VoucherClaim,
     *     selection: VoucherClaimOutcomeSelection,
     *     reservation: TreasuryPositionOperation,
     *     release: TreasuryPositionOperation
     * }  $context
     * @param  list<string>  $events
     * @return array<string, mixed>
     */
    private function result(
        array $context,
        array $events,
        bool $committed,
    ): array {
        $references = ExecutionJournalEntry::query()
            ->whereIn('idempotency_key', [
                'x-change:account-funding-pay-code:outcome-selected:'
                    .$context['selection']->getKey(),
                'x-change:account-funding-pay-code:applied:'
                    .$context['claim']->getKey(),
            ])
            ->orderBy('id')
            ->pluck('reference_number')
            ->all();

        return [
            'schema' => 'x-change.account-funding-pay-code-journal-backfill.v1',
            'success' => true,
            'status' => match (true) {
                ! $committed && $events === [] => 'already_complete',
                ! $committed => 'ready',
                $events === [] => 'already_complete',
                default => 'backfilled',
            },
            'voucher_id' => (int) $context['voucher']->getKey(),
            'claim_id' => (int) $context['claim']->getKey(),
            'selection_id' => (int) $context['selection']->getKey(),
            'amount_minor' => (int) $context['claim']->disbursed_amount_minor,
            'currency' => (string) $context['claim']->currency,
            'reservation_operation_reference' => $context['reservation']->operation_reference,
            'release_operation_reference' => $context['release']->operation_reference,
            'event_count' => count($events),
            'events' => $events,
            'journal_references' => $references,
            'committed' => $committed,
            'provider_calls' => false,
            'treasury_changed' => false,
        ];
    }
}
