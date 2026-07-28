<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Treasury\PayCodeTerminalReleaseData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class PayCodeTerminalReleaseJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function record(
        Voucher $voucher,
        Model $owner,
        PayCodeTerminalReleaseData $release,
        ?string $reservationOperationReference,
    ): void {
        if (
            $release->status !== 'released'
            || $release->operationReference === null
            || $release->currency === null
        ) {
            return;
        }

        $voucherId = (string) $voucher->getKey();
        $correlationId = "pay-code-terminal:{$release->terminalReason}:{$voucherId}";

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'pay_code.reserve.released',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: (string) $owner->getKey(),
                type: $owner::class,
            ),
            subject: new ExecutionSubjectData(
                id: $voucherId,
                type: 'voucher',
                display: (string) $voucher->code,
            ),
            references: new ExecutionReferenceData(
                correlationId: $correlationId,
                causationId: $reservationOperationReference,
                executionId: $voucherId,
                externalReference: $release->operationReference,
                metadata: [
                    'reservation_operation_reference' => $reservationOperationReference,
                    'release_operation_reference' => $release->operationReference,
                ],
            ),
            idempotencyKey: 'x-change:pay-code:terminal-release:'
                .$release->terminalReason.':'.$voucherId,
            payload: [
                'status' => $release->status,
                'terminal_reason' => $release->terminalReason,
                'provider_calls' => false,
                'provider_inventory_changed' => false,
                'issuance_charges_refunded' => false,
            ],
            money: new ExecutionMoneyData(
                currency: $release->currency,
                minorAmount: $release->amountMinor,
            ),
            metadata: [
                'schema' => 'x-change.pay-code-terminal-release-journal.v1',
                'domain' => 'pay_code',
                'source' => 'pay_code_terminal_release',
                'accounting_authority' => 'treasury_position_operations',
            ],
        ));
    }
}
