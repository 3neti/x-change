<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Data\PayoutResultData;

final class PayoutResultAudit
{
    /** @return array<string, mixed> */
    public function summarize(PayoutResultData $result): array
    {
        $summary = [
            'transaction_id' => $result->transaction_id,
            'uuid' => $result->uuid,
            'status' => $result->status->value,
            'provider' => $result->provider,
        ];
        $accepted = data_get($result->metadata, 'provider_submission_accepted');
        if (is_bool($accepted)) {
            $summary['metadata'] = ['provider_submission_accepted' => $accepted];
        }

        return $summary;
    }
}
