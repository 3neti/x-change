<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\ApprovalHandlers;

use LBHurtado\XChange\Contracts\ApprovalRequirementHandlerContract;

class ManualApprovalRequirementHandler implements ApprovalRequirementHandlerContract
{
    public function requirement(): string
    {
        return 'approval';
    }

    public function handle(array $meta = [], array $context = []): array
    {
        return [
            'type' => 'approval',
            'status' => 'pending',
            'label' => 'Manual approval required',
            'message' => 'This withdrawal requires approval before it can proceed.',
            'meta' => $meta,
        ];
    }
}
