<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Data\Commercial\CommercialRecognitionPolicyData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use Throwable;

final class CommercialRecognitionPolicyRegistry
{
    public function resolve(string $reference, string $billableEventReference): CommercialRecognitionPolicyData
    {
        $policy = config('x-change.commercial.recognition_policies.policies.'.trim($reference));

        if (! is_array($policy)) {
            throw new CommercialSaleConflict("Recognition policy [{$reference}] is not governed or active.");
        }

        try {
            $resolved = new CommercialRecognitionPolicyData(
                reference: trim($reference),
                version: (int) ($policy['version'] ?? 0),
                billableEventReferences: array_values((array) ($policy['billable_event_references'] ?? [])),
                trigger: (string) ($policy['trigger'] ?? ''),
                timing: (string) ($policy['timing'] ?? ''),
            );
        } catch (Throwable $exception) {
            throw new CommercialSaleConflict(
                "Recognition policy [{$reference}] is malformed.",
                previous: $exception,
            );
        }

        if (! in_array($billableEventReference, $resolved->billableEventReferences, true)) {
            throw new CommercialSaleConflict(
                "Recognition policy [{$reference}] does not authorize Billable Event [{$billableEventReference}].",
            );
        }

        return $resolved;
    }
}
