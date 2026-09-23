<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CampaignPolicyCompletionAuthorityContract;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Events\PolicyCompletionRequested;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final readonly class RequestCampaignPolicyCompletion
{
    public function __construct(
        private PrepareCampaignPolicyCompletion $prepare,
        private CampaignPolicyCompletionAuthorityContract $authority,
    ) {}

    public function handle(
        CompletionClaimEvidenceProjection $projection,
        Model $requester,
        string $authorizationReference,
    ): PolicyCompletionRequest {
        if (! $this->authority->mayRequest($requester)) {
            throw new AuthorizationException('Policy completion maker authority is required.');
        }

        $authorizationReference = trim($authorizationReference);
        if ($authorizationReference === '') {
            throw new DomainException('Policy completion requires an authorization reference.');
        }

        $prepared = $this->prepare->handle($projection);
        $projection->loadMissing(['issuance.coverage.recognition.binding.standingFundingAddress', 'issuance.coverage.recognition.source.campaign.owner']);
        $owner = $projection->issuance->coverage->recognition->ownerRecord();
        if (! in_array($owner->getMorphClass(), [$requester->getMorphClass(), $requester::class], true)
            || (string) $owner->getKey() !== (string) $requester->getKey()) {
            throw new AuthorizationException('Only the campaign owner may request policy completion.');
        }

        $result = DB::transaction(function () use ($projection, $requester, $authorizationReference, $prepared): array {
            $locked = CompletionClaimEvidenceProjection::query()->lockForUpdate()->findOrFail($projection->getKey());
            $existing = PolicyCompletionRequest::query()
                ->where('completion_claim_evidence_projection_id', $locked->getKey())
                ->first();

            if ($existing instanceof PolicyCompletionRequest) {
                $matches = $existing->idempotency_key === $prepared->idempotencyKey
                    && $existing->preparation_fingerprint === $prepared->fingerprint
                    && $existing->requester_type === $requester->getMorphClass()
                    && (string) $existing->requester_id === (string) $requester->getKey()
                    && $existing->authorization_reference === $authorizationReference;
                if (! $matches) {
                    throw new DomainException('Existing policy completion request does not match this replay.');
                }

                return ['request' => $existing, 'created' => false];
            }

            $request = PolicyCompletionRequest::query()->create([
                'completion_claim_evidence_projection_id' => $locked->getKey(),
                'driver_id' => $prepared->driverId,
                'driver_version' => $prepared->driverVersion,
                'idempotency_key' => $prepared->idempotencyKey,
                'preparation_fingerprint' => $prepared->fingerprint,
                'safe_context' => $prepared->safeContext(),
                'private_payload' => $prepared->privateApplicantEvidence(),
                'status' => PolicyCompletionRequestStatus::AwaitingApproval,
                'requester_type' => $requester->getMorphClass(),
                'requester_id' => $requester->getKey(),
                'authorization_reference' => $authorizationReference,
                'requested_at' => now(),
            ]);

            return ['request' => $request, 'created' => true];
        }, attempts: 5);

        if ($result['created']) {
            PolicyCompletionRequested::dispatch($this->eventPayload($result['request']));
        }

        return $result['request'];
    }

    /** @return array<string, int|string|null> */
    private function eventPayload(PolicyCompletionRequest $request): array
    {
        return [
            'schema' => 'x-change.policy-completion-requested.v1',
            'request_reference' => $request->reference,
            'projection_reference' => (string) data_get($request->safe_context, 'projection_reference'),
            'driver_id' => $request->driver_id,
            'driver_version' => $request->driver_version,
            'status' => $request->status->value,
        ];
    }
}
