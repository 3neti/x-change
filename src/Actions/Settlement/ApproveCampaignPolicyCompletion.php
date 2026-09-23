<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CampaignPolicyCompletionAuthorityContract;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Events\PolicyCompletionAuthorized;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final readonly class ApproveCampaignPolicyCompletion
{
    public function __construct(
        private PrepareCampaignPolicyCompletion $prepare,
        private CampaignPolicyCompletionAuthorityContract $authority,
    ) {}

    public function handle(
        PolicyCompletionRequest $request,
        Model $approver,
        string $approvalReference,
    ): PolicyCompletionRequest {
        if (! $this->authority->mayApprove($approver)) {
            throw new AuthorizationException('Policy completion checker authority is required.');
        }
        $approvalReference = trim($approvalReference);
        if ($approvalReference === '') {
            throw new DomainException('Policy completion approval requires a reference.');
        }

        $result = DB::transaction(function () use ($request, $approver, $approvalReference): array {
            $locked = PolicyCompletionRequest::query()->with('projection')->lockForUpdate()->findOrFail($request->getKey());
            if ($locked->requester_type === $approver->getMorphClass()
                && (string) $locked->requester_id === (string) $approver->getKey()) {
                throw new DomainException('The policy completion checker must be independent from its maker.');
            }
            if ($locked->status !== PolicyCompletionRequestStatus::AwaitingApproval) {
                $matches = $locked->status === PolicyCompletionRequestStatus::Authorized
                    && $locked->approver_type === $approver->getMorphClass()
                    && (string) $locked->approver_id === (string) $approver->getKey()
                    && $locked->approval_reference === $approvalReference;
                if ($matches) {
                    return ['request' => $locked, 'changed' => false];
                }
                throw new DomainException('Policy completion request is not awaiting approval.');
            }

            $prepared = $this->prepare->handle($locked->projection);
            if (! hash_equals($locked->preparation_fingerprint, $prepared->fingerprint)) {
                throw new DomainException('Policy completion preparation changed before approval.');
            }

            $locked->forceFill([
                'status' => PolicyCompletionRequestStatus::Authorized,
                'approver_type' => $approver->getMorphClass(),
                'approver_id' => $approver->getKey(),
                'approval_reference' => $approvalReference,
                'approved_at' => now(),
            ])->saveQuietly();

            return ['request' => $locked->refresh(), 'changed' => true];
        }, attempts: 5);

        if ($result['changed']) {
            PolicyCompletionAuthorized::dispatch([
                'schema' => 'x-change.policy-completion-authorized.v1',
                'request_reference' => $result['request']->reference,
                'driver_id' => $result['request']->driver_id,
                'driver_version' => $result['request']->driver_version,
                'status' => $result['request']->status->value,
            ]);
        }

        return $result['request'];
    }
}
