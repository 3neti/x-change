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
            return new SettlementEnvelopeReadinessData(
                required: false,
                exists: false,
                ready: true,
                driver: $profile->driver,
                gate: $profile->gate,
                satisfied: [],
                missing: [],
                failed: [],
                warnings: [],
                checklist: [],
                payload: $evidence->payload,
                documents: $evidence->documents,
                meta: [
                    ...$profile->meta,
                    ...$evidence->meta,
                    'requires_envelope' => false,
                    'evaluated_at' => now()->toISOString(),
                ],
            );
        }

        $gateConditions = $profile->gate_conditions;

        $checklist = $this->buildChecklist(
            profile: $profile,
            evidence: $evidence,
        );

        $satisfied = [];
        $missing = [];
        $failed = [];
        $warnings = [];

        foreach ($gateConditions as $condition) {
            if ($this->conditionSatisfied($condition, $profile, $evidence, $checklist)) {
                $satisfied[] = $condition;

                continue;
            }

            $missing[] = $condition;
        }

        foreach ($checklist as $id => $item) {
            if (($item['satisfied'] ?? false) === true) {
                if (! in_array($id, $satisfied, true)) {
                    $satisfied[] = $id;
                }

                continue;
            }

            if (($item['required'] ?? false) === true && in_array($id, $gateConditions, true)) {
                if (! in_array($id, $missing, true)) {
                    $missing[] = $id;
                }
            }
        }

        $satisfied = array_values(array_unique($satisfied));
        $missing = array_values(array_unique($missing));
        $failed = array_values(array_unique($failed));
        $warnings = array_values(array_unique($warnings));

        return new SettlementEnvelopeReadinessData(
            required: true,
            exists: true,
            ready: $missing === [] && $failed === [],
            driver: $profile->driver,
            gate: $profile->gate,
            satisfied: $satisfied,
            missing: $missing,
            failed: $failed,
            warnings: $warnings,
            checklist: $checklist,
            payload: $evidence->payload,
            documents: $evidence->documents,
            meta: [
                ...$profile->meta,
                ...$evidence->meta,
                'requires_envelope' => true,
                'evaluated_at' => now()->toISOString(),
            ],
        );
    }

    protected function buildChecklist(
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
    ): array {
        $items = [];

        foreach ($profile->checklist_items as $id => $item) {
            if (is_string($item)) {
                $id = $item;
                $item = [];
            }

            $items[$id] = [
                'id' => $id,
                'label' => Arr::get($item, 'label', str($id)->headline()->toString()),
                'description' => Arr::get($item, 'description'),
                'auto' => (bool) Arr::get($item, 'auto', false),
                'required' => in_array($id, $profile->gate_conditions, true),
                'satisfied' => $this->conditionSatisfied($id, $profile, $evidence, []),
            ];
        }

        foreach ($profile->gate_conditions as $condition) {
            if (isset($items[$condition])) {
                continue;
            }

            $items[$condition] = [
                'id' => $condition,
                'label' => str($condition)->headline()->toString(),
                'description' => null,
                'auto' => true,
                'required' => true,
                'satisfied' => $this->conditionSatisfied($condition, $profile, $evidence, $items),
            ];
        }

        return $items;
    }

    protected function conditionSatisfied(
        string $condition,
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
        array $checklist,
    ): bool {
        return match ($condition) {
            'payload_present' => $this->payloadPresent($profile, $evidence),
            'documents_uploaded',
            'claim_documents_uploaded' => $this->documentsPresent($evidence),
            default => $this->checklistSatisfied($condition, $evidence),
        };
    }

    protected function payloadPresent(
        SettlementEnvelopeProfileData $profile,
        SettlementEnvelopeEvidenceData $evidence,
    ): bool {
        if ($profile->required_payload_fields === []) {
            return $this->hasNonEmptyValues($evidence->payload);
        }

        foreach ($profile->required_payload_fields as $field) {
            if (! filled(Arr::get($evidence->payload, $field))) {
                return false;
            }
        }

        return true;
    }

    protected function documentsPresent(
        SettlementEnvelopeEvidenceData $evidence,
    ): bool {
        return $this->hasNonEmptyValues($evidence->documents);
    }

    protected function checklistSatisfied(
        string $condition,
        SettlementEnvelopeEvidenceData $evidence,
    ): bool {
        return filter_var(
            Arr::get($evidence->checklist, $condition, false),
            FILTER_VALIDATE_BOOL,
        );
    }

    protected function hasNonEmptyValues(array $values): bool
    {
        foreach ($values as $value) {
            if (is_array($value) && $this->hasNonEmptyValues($value)) {
                return true;
            }

            if (! is_array($value) && filled($value)) {
                return true;
            }
        }

        return false;
    }
}
