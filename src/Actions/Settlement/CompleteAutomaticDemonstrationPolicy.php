<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use DomainException;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Events\PolicyCompletionRequested;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Models\PolicyCompletionRequest;
use LBHurtado\XChange\Services\Settlement\AutomaticDemonstrationPolicy;
use LBHurtado\XChange\Services\Settlement\DispatchAuiDemonstrationPolicyViaPipedream;

final readonly class CompleteAutomaticDemonstrationPolicy
{
    public function __construct(
        private AutomaticDemonstrationPolicy $policy,
        private PrepareCampaignPolicyCompletion $prepare,
        private DispatchAuiDemonstrationPolicyViaPipedream $transport,
        private RecordCampaignPolicyCompletionOutcome $record,
    ) {}

    public function handle(CompletionClaimEvidenceProjection $projection): PolicyCompletionOutcome
    {
        $this->policy->assertEnabled($projection);
        $prepared = $this->prepare->handle($projection);
        $owner = $projection->issuance->coverage->recognition->ownerRecord();
        [$request, $created] = DB::transaction(function () use ($projection, $prepared, $owner): array {
            CompletionClaimEvidenceProjection::query()->lockForUpdate()->findOrFail($projection->getKey());
            $existing = $projection->policyCompletionRequest()->first();
            if ($existing !== null) {
                if (! $this->policy->matches($existing)
                    || ! hash_equals($existing->preparation_fingerprint, $prepared->fingerprint)) {
                    throw new DomainException('Existing policy request does not match automatic demonstration completion.');
                }

                return [$existing, false];
            }

            return [PolicyCompletionRequest::query()->create([
                'completion_claim_evidence_projection_id' => $projection->getKey(),
                'driver_id' => $prepared->driverId,
                'driver_version' => $prepared->driverVersion,
                'idempotency_key' => $prepared->idempotencyKey,
                'preparation_fingerprint' => $prepared->fingerprint,
                'safe_context' => $prepared->safeContext() + ['authorization_mode' => AutomaticDemonstrationPolicy::MODE],
                'private_payload' => $prepared->privateApplicantEvidence(),
                'status' => PolicyCompletionRequestStatus::Authorized,
                'requester_type' => $owner->getMorphClass(),
                'requester_id' => $owner->getKey(),
                'authorization_reference' => 'automatic-demo:'.$projection->reference,
                'requested_at' => now(),
            ]), true];
        }, attempts: 5);

        if ($created) {
            PolicyCompletionRequested::dispatch([
                'schema' => 'x-change.policy-completion-requested.v1',
                'request_reference' => $request->reference,
                'projection_reference' => $projection->reference,
                'driver_id' => $request->driver_id,
                'driver_version' => $request->driver_version,
                'status' => $request->status->value,
                'authorization_mode' => AutomaticDemonstrationPolicy::MODE,
            ]);
        }

        if ($request->outcome !== null) {
            return $request->outcome;
        }

        $response = $this->transport->handle($prepared);

        return $this->record->handleAutomaticDemonstration($request, $response);
    }
}
