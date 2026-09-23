<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CampaignPolicyCompletionAuthorityContract
{
    public function mayRequest(Model $operator): bool;

    public function mayApprove(Model $operator): bool;

    public function mayRecordOutcome(Model $operator): bool;
}
