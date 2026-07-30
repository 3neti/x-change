<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Operations;

use LBHurtado\XChange\Models\ExternalJobFailure;
use Throwable;

final class RecordExternalJobFailure
{
    public function handle(
        string $jobType,
        string $subjectType,
        string|int $subjectId,
        Throwable $failure,
        ?string $providerCode = null,
        ?string $trigger = null,
    ): ExternalJobFailure {
        return ExternalJobFailure::query()->create([
            'job_type' => $jobType,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'provider_code' => $providerCode,
            'trigger' => $trigger,
            'failure_type' => class_basename($failure),
            'failed_at' => now(),
        ]);
    }
}
