<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeEvidenceData;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeProfileData;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

class SettlementEnvelopeEvaluationEngine
{
    public function evaluate(
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
    ): SettlementEnvelopeReadinessData {
        if (! $profile->requires_envelope) {
            return SettlementEnvelopeReadinessData::notRequired($profile->meta);
        }

        $checklist = $this->evaluateChecklist($profile, $evidence);

        $satisfied = [];
        $missing = [];
        $failed = [];

        foreach ($profile->gate_conditions as $condition) {
            $passed = (bool) Arr::get($checklist, "{$condition}.satisfied", false);

            if ($passed) {
                $satisfied[] = $condition;
            } else {
                $missing[] = $condition;
            }
        }

        return new SettlementEnvelopeReadinessData(
            required: true,
            exists: true,
            ready: $missing === [] && $failed === [],
            driver: $profile->driver,
            gate: $profile->gate,
            satisfied: $satisfied,
            missing: $missing,
            failed: $failed,
            warnings: [],
            checklist: $checklist,
            payload: $evidence->payload,
            documents: $evidence->documents,
            meta: [
                ...$profile->meta,
                ...$evidence->meta,
                'evaluated_at' => now()->toISOString(),
            ],
        );
    }

    protected function evaluateChecklist(
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
    ): array {
        $result = [];

        foreach ($profile->checklist_items as $id => $item) {
            $auto = (bool) ($item['auto'] ?? false);

            $result[$id] = [
                'id' => $id,
                'label' => $item['label'] ?? $id,
                'auto' => $auto,
                'satisfied' => $auto
                    ? $this->evaluateAutoChecklistItem($id, $profile, $evidence)
                    : (bool) Arr::get($evidence->checklist, $id, false),
                'description' => $item['description'] ?? null,
            ];
        }

        return $result;
    }

    protected function evaluateAutoChecklistItem(
        string $id,
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
    ): bool {
        return match ($id) {
            'payload_present' => $this->payloadPresent($profile, $evidence),
            'claim_documents_uploaded' => $this->claimDocumentsUploaded($evidence),
            default => (bool) Arr::get($evidence->checklist, $id, false),
        };
    }

    protected function payloadPresent(
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
    ): bool {
        if ($profile->required_payload_fields === []) {
            return $evidence->payload !== [];
        }

        foreach ($profile->required_payload_fields as $field) {
            $value = Arr::get($evidence->payload, $field);

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    protected function claimDocumentsUploaded(SettlementEnvelopeEvidenceData $evidence): bool
    {
        return collect($evidence->documents)
            ->filter(fn ($document): bool => filled($document))
            ->isNotEmpty();
    }
}
