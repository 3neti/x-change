<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;

final class LifecycleApprovalRequiredResult
{
    public function isApprovalRequired(mixed $claim): bool
    {
        return $claim instanceof ClaimApprovalInitiationResultData
            || data_get($claim, 'status') === 'approval_required'
            || data_get($claim, 'status') === 'pending_approval';
    }

    /**
     * @return array<string, mixed>
     */
    public function toActual(mixed $claim): array
    {
        $claimArray = method_exists($claim, 'toArray')
            ? $claim->toArray()
            : (array) $claim;

        $metadata = (array) (
        data_get($claimArray, 'approval_metadata')
            ?: data_get($claimArray, 'meta')
            ?: []
        );

        $provider = (string) data_get($metadata, 'provider', data_get($claimArray, 'provider', 'approval'));
        $authorizationType = (string) data_get($metadata, 'authorization_type', 'approval');
        $referenceId = data_get($metadata, 'reference_id', data_get($claimArray, 'reference_id'));

        return [
            'status' => 'pending_approval',
            'message' => trim(sprintf(
                'Approval required: %s %s%s',
                $provider,
                strtoupper($authorizationType),
                $referenceId ? " [{$referenceId}]" : '',
            )),
            'claim' => $claimArray,
            'approval' => $metadata,
            'disbursement_check' => null,
        ];
    }
}
