<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\CampaignPolicyCompletionAuthorityContract;

final class ConfigCampaignPolicyCompletionAuthority implements CampaignPolicyCompletionAuthorityContract
{
    public function mayRequest(Model $operator): bool
    {
        return $this->allows($operator, 'maker_ids');
    }

    public function mayApprove(Model $operator): bool
    {
        return $this->allows($operator, 'checker_ids');
    }

    public function mayRecordOutcome(Model $operator): bool
    {
        return $this->allows($operator, 'outcome_recorder_ids');
    }

    private function allows(Model $operator, string $key): bool
    {
        $modelClass = config('x-change.onboarding.issuer_model')
            ?: config('auth.providers.users.model');

        if (! is_string($modelClass) || $modelClass === '' || ! is_a($operator, $modelClass)) {
            return false;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) config("x-change.settlement.policy_completion.{$key}", []),
        ))));

        return in_array((string) $operator->getKey(), $ids, true);
    }
}
