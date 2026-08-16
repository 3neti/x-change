<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Data\Commercial\CommercialRecognitionPolicyData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialRecognitionPolicy;

final class PersistCommercialRecognitionPolicy
{
    public function execute(CommercialRecognitionPolicyData $policy): CommercialRecognitionPolicy
    {
        $persisted = CommercialRecognitionPolicy::query()->firstOrCreate([
            'reference' => trim($policy->reference),
            'version' => $policy->version,
        ], [
            'trigger' => $policy->trigger,
            'timing' => $policy->timing,
            'snapshot_hash' => $policy->snapshotHash(),
            'snapshot' => $policy->toArray(),
        ]);

        if (! hash_equals($persisted->snapshot_hash, $policy->snapshotHash())) {
            throw new CommercialSaleConflict(
                "Recognition policy [{$policy->reference}] version [{$policy->version}] changed without a new version.",
            );
        }

        return $persisted;
    }
}
