<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;

final class CoverageCompletionSuccessPresentation
{
    /** @return array<string, mixed> */
    public function forVoucher(Voucher $voucher): array
    {
        $issuance = $voucher->exists ? CompletionPayCodeIssuance::query()
            ->with(['envelope', 'coverage.recognition', 'evidenceProjection.claim', 'evidenceProjection.policyCompletionRequest.outcome'])
            ->where('voucher_id', $voucher->getKey())
            ->first() : null;
        $completion = data_get($voucher->metadata, 'instructions.execution.metadata.completion', []);
        $coverage = $issuance?->coverage;
        $recognition = $coverage?->recognition;

        if ($issuance === null || $coverage === null || $issuance->envelope === null
            || data_get($completion, 'schema') !== 'x-change.campaign-coverage-completion.v1'
            || data_get($completion, 'coverage_reference') !== $coverage->reference
            || data_get($completion, 'envelope_reference') !== $issuance->envelope->reference_code
            || data_get($completion, 'driver_id') !== $issuance->driver_id
            || data_get($completion, 'driver_version') !== $issuance->driver_version
            || (string) $coverage->envelope_id !== (string) $issuance->envelope_id
            || $recognition === null || $recognition->recognized_at === null
            || ! $recognition->destination_verified || $recognition->gross_amount_minor <= 0) {
            return $this->forState('payment_unverified');
        }

        $projection = $issuance->evidenceProjection;
        if ($projection === null || $projection->projected_at === null
            || (string) $projection->envelope_id !== (string) $issuance->envelope_id
            || (string) $projection->claim?->voucher_id !== (string) $voucher->getKey()) {
            return $this->forState('details_required');
        }

        $request = $projection->policyCompletionRequest;
        $outcome = $request?->outcome;
        if ($request?->status === PolicyCompletionRequestStatus::Succeeded
            && $outcome?->status === PolicyCompletionOutcomeStatus::Succeeded
            && $outcome->recorded_at !== null) {
            return $this->forState('ready');
        }

        if ($request?->status?->terminal() || $outcome !== null) {
            return $this->forState('needs_attention');
        }

        return $this->forState('processing');
    }

    /** @return array<string, mixed> */
    public function forState(string $state): array
    {
        return match ($state) {
            'payment_unverified' => $this->presentation('payment_unverified', 'Payment verification unavailable', 'We cannot verify the payment linked to this details request. Please contact the campaign operator. Do not pay again.'),
            'details_required' => $this->presentation('details_required', 'Complete your details', 'Your payment has been received. Complete the required personal details to continue.'),
            'processing' => $this->presentation('processing', 'Details submitted', 'Your payment has been received. Your policy result is being prepared. You will be notified when it is ready.'),
            'ready' => $this->presentation('ready', 'Policy result ready', 'Your payment has been received and your details have been submitted. Your policy result is ready. Use the link provided by the campaign. A demonstration result is not actual insurance coverage.'),
            'needs_attention' => $this->presentation('needs_attention', 'Details submitted — processing needs attention', 'Your payment has been received. The policy result is not confirmed. Please contact the campaign operator; do not pay again.'),
            default => throw new \InvalidArgumentException("Unsupported coverage completion presentation state [{$state}]."),
        };
    }

    /** @return array<string, mixed> */
    private function presentation(string $state, string $title, string $body): array
    {
        return [
            'schema' => 'x-change.coverage-completion-success-presentation.v1',
            'state' => $state,
            'title_template' => $title,
            'body' => $body,
            'suppress_legacy_rider' => true,
        ];
    }
}
