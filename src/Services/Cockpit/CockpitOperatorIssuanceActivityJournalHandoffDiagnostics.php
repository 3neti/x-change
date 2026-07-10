<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;

class CockpitOperatorIssuanceActivityJournalHandoffDiagnostics
{
    /**
     * @return array{
     *     classification: string,
     *     tone: string,
     *     label: string,
     *     description: string,
     *     operator_action: string,
     *     read_only: bool,
     *     retry_enabled: bool,
     *     mutation_enabled: bool,
     *     raw_payloads_exposed: bool
     * }
     */
    public function classify(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): array
    {
        return match ($result->status) {
            'recorded' => $this->recorded($result),
            'failed_non_blocking' => $this->failedNonBlocking($result),
            'not_wired' => $this->notWired($result),
            default => $this->unknown($result),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function recorded(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): array
    {
        return $this->base(
            classification: 'recorded',
            tone: 'success',
            label: 'Journal recorded',
            description: $result->journal_entry_id
                ? 'The durable activity was handed to the journal and a journal entry identifier is available for read-only inspection.'
                : 'The durable activity was handed to the journal. No journal entry identifier was returned.',
            operatorAction: 'none',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function failedNonBlocking(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): array
    {
        $exception = $this->safeString($result->metadata['exception'] ?? null);

        return $this->base(
            classification: 'failed_non_blocking',
            tone: 'warning',
            label: 'Journal handoff failed non-blocking',
            description: $exception
                ? sprintf('The durable activity was preserved, but journal handoff failed non-blocking: %s.', $exception)
                : 'The durable activity was preserved, but journal handoff failed non-blocking.',
            operatorAction: 'review_configuration',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function notWired(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): array
    {
        return $this->base(
            classification: 'not_wired',
            tone: 'neutral',
            label: 'Journal handoff not wired',
            description: $result->reason,
            operatorAction: 'configure_when_ready',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function unknown(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): array
    {
        return $this->base(
            classification: 'unknown',
            tone: 'neutral',
            label: 'Journal handoff status unknown',
            description: sprintf('Journal handoff returned unrecognized status `%s`; display is read-only.', $result->status),
            operatorAction: 'monitor',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function base(
        string $classification,
        string $tone,
        string $label,
        string $description,
        string $operatorAction,
    ): array {
        return [
            'classification' => $classification,
            'tone' => $tone,
            'label' => $label,
            'description' => $description,
            'operator_action' => $operatorAction,
            'read_only' => true,
            'retry_enabled' => false,
            'mutation_enabled' => false,
            'raw_payloads_exposed' => false,
        ];
    }

    private function safeString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
