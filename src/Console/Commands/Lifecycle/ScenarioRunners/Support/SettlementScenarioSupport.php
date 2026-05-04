<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

final class SettlementScenarioSupport
{
    public function formatReadiness(SettlementEnvelopeReadinessData $readiness): array
    {
        return [
            'required' => $readiness->required,
            'exists' => $readiness->exists,
            'ready' => $readiness->ready,
            'driver' => $readiness->driver,
            'gate' => $readiness->gate,
            'satisfied' => $readiness->satisfied,
            'missing' => $readiness->missing,
            'failed' => $readiness->failed,
            'warnings' => $readiness->warnings,
            'checklist' => $readiness->checklist,
            'payload' => $readiness->payload,
            'documents' => $readiness->documents,
            'meta' => $readiness->meta,
        ];
    }
}
