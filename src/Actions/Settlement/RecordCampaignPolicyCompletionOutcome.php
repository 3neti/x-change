<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CampaignPolicyCompletionAuthorityContract;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionOutcomeData;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Events\PolicyCompletionOutcomeRecorded;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Models\PolicyCompletionRequest;
use LBHurtado\XChange\Services\Settlement\AutomaticDemonstrationPolicy;

final readonly class RecordCampaignPolicyCompletionOutcome
{
    public function __construct(
        private PrepareCampaignPolicyCompletion $prepare,
        private CampaignPolicyCompletionAuthorityContract $authority,
    ) {}

    public function handle(
        PolicyCompletionRequest $request,
        Model $recorder,
        PolicyCompletionOutcomeData $data,
    ): PolicyCompletionOutcome {
        if (! $this->authority->mayRecordOutcome($recorder)) {
            throw new AuthorizationException('Policy completion outcome authority is required.');
        }

        return $this->record($request, $recorder, $data);
    }

    public function handleAutomaticDemonstration(PolicyCompletionRequest $request, AuiDemonstrationPolicyResponseData $response): PolicyCompletionOutcome
    {
        $policy = new AutomaticDemonstrationPolicy;
        $policy->assertEnabled($request->projection);
        if (! $policy->matches($request)) {
            throw new DomainException('Automatic demonstration request provenance is required.');
        }
        $owner = $request->projection->issuance->coverage->recognition->ownerRecord();
        if ($request->requester_type !== $owner->getMorphClass()
            || (string) $request->requester_id !== (string) $owner->getKey()) {
            throw new DomainException('Automatic demonstration owner attribution does not match.');
        }

        return $this->record($request, $owner, $response->outcome());
    }

    private function record(PolicyCompletionRequest $request, Model $recorder, PolicyCompletionOutcomeData $data): PolicyCompletionOutcome
    {
        if (trim($data->resultCode) === '') {
            throw new DomainException('Policy completion outcome requires a result code.');
        }
        $this->assertSafeResult($data->safeResult);

        $snapshot = $this->canonical([
            'status' => $data->status->value,
            'result_code' => trim($data->resultCode),
            'provider_reference' => $this->nullableString($data->providerReference),
            'safe_result' => $data->safeResult,
            'private_result' => $data->privateResult,
        ]);
        $outcomeHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $result = DB::transaction(function () use ($request, $recorder, $data, $snapshot, $outcomeHash): array {
            $locked = PolicyCompletionRequest::query()->with(['projection', 'outcome'])->lockForUpdate()->findOrFail($request->getKey());
            if ($locked->outcome instanceof PolicyCompletionOutcome) {
                if (! hash_equals($locked->outcome->outcome_hash, $outcomeHash)
                    || $locked->outcome->recorded_by_type !== $recorder->getMorphClass()
                    || (string) $locked->outcome->recorded_by_id !== (string) $recorder->getKey()) {
                    throw new DomainException('Existing policy completion outcome does not match this replay.');
                }

                return ['outcome' => $locked->outcome, 'created' => false];
            }
            if ($locked->status !== PolicyCompletionRequestStatus::Authorized) {
                throw new DomainException('Only an authorized policy completion request may receive an outcome.');
            }

            $prepared = $this->prepare->handle($locked->projection);
            if (! hash_equals($locked->preparation_fingerprint, $prepared->fingerprint)) {
                throw new DomainException('Policy completion preparation changed before outcome recording.');
            }

            $outcome = PolicyCompletionOutcome::query()->create([
                'policy_completion_request_id' => $locked->getKey(),
                'status' => $data->status,
                'result_code' => $snapshot['result_code'],
                'provider_reference' => $snapshot['provider_reference'],
                'safe_result' => $snapshot['safe_result'],
                'private_result' => $snapshot['private_result'] === [] ? null : $snapshot['private_result'],
                'outcome_hash' => $outcomeHash,
                'recorded_by_type' => $recorder->getMorphClass(),
                'recorded_by_id' => $recorder->getKey(),
                'recorded_at' => now(),
            ]);
            $locked->forceFill(['status' => $data->status->requestStatus()])->saveQuietly();

            return ['outcome' => $outcome, 'created' => true];
        }, attempts: 5);

        if ($result['created']) {
            $outcome = $result['outcome'];
            PolicyCompletionOutcomeRecorded::dispatch([
                'schema' => 'x-change.policy-completion-outcome-recorded.v1',
                'request_reference' => $outcome->request->reference,
                'outcome_reference' => $outcome->reference,
                'status' => $outcome->status->value,
                'result_code' => $outcome->result_code,
                'provider_reference' => $outcome->provider_reference,
            ]);
        }

        return $result['outcome'];
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, int|string|bool|null> $safeResult */
    private function assertSafeResult(array $safeResult): void
    {
        $allowed = [
            'decision',
            'document_ready',
            'provider_status',
            'reason_code',
            'retryable',
        ];

        foreach ($safeResult as $key => $value) {
            if (! in_array($key, $allowed, true)
                || (! is_scalar($value) && $value !== null)) {
                throw new DomainException(
                    "Policy completion safe result field [{$key}] is not allowed.",
                );
            }
        }
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonical(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }
        ksort($value);

        return $value;
    }
}
