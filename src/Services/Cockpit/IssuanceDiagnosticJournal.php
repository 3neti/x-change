<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

final readonly class IssuanceDiagnosticJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    /** @param array<string, mixed> $payload */
    public function record(Request $request, string $reference, string $event, array $payload): bool
    {
        $actor = $request->user();
        if (! $actor instanceof Model) {
            return false;
        }
        $key = 'issuance-diagnostic:'.$actor->getMorphClass().':'.$actor->getKey().':'.$reference.':'.$event;
        try {
            if (ExecutionJournalEntry::query()->where('idempotency_key', $key)->exists()) {
                return true;
            }
            $this->recorder->record(new ExecutionJournalEntryData(
                eventType: 'issuance.'.$event,
                occurredAt: CarbonImmutable::now(),
                actor: new ExecutionActorData(id: (string) $actor->getKey(), type: $actor->getMorphClass()),
                subject: new ExecutionSubjectData(id: $reference, type: 'issuance_attempt'),
                references: new ExecutionReferenceData(correlationId: $reference),
                idempotencyKey: $key,
                payload: $payload,
                metadata: ['schema' => 'x-change.issuance-diagnostic.v1'],
            ));

            return true;
        } catch (Throwable $error) {
            try {
                if (ExecutionJournalEntry::query()->where('idempotency_key', $key)->exists()) {
                    return true;
                }
            } catch (Throwable) {
                // Diagnostics must remain non-blocking when journal storage is unavailable.
            }
            Log::warning('Issuance diagnostic could not be recorded', ['reference' => $reference, 'exception_class' => $error::class]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function requestEvidence(Request $request): array
    {
        $url = $request->input('rider.url');
        $url = is_string($url) ? trim($url) : '';
        $templateReference = $request->input('metadata.custom.cockpit.saved_template.reference');
        $actor = $request->user();
        $template = $actor instanceof Model && is_string($templateReference)
            ? PayCodeTemplate::query()->whereMorphedTo('owner', $actor)->where('reference', $templateReference)->first()
            : null;
        $original = $template ? data_get($template->instructions_ciphertext, 'rider.url') : null;

        return [
            'source' => 'server_confirmed',
            'template_reference' => $template?->reference,
            'submitted_url' => ['present' => $url !== '', 'length' => mb_strlen($url), 'fingerprint' => $url === '' ? null : hash('sha256', $url)],
            'template_url_changed' => $template ? trim(is_string($original) ? $original : '') !== $url : null,
        ];
    }
}
