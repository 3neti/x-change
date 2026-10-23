<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Support\Arrayable;
use LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\RedemptionContractEngine;
use LBHurtado\XChange\Exceptions\IncompleteClaimEvidence;
use LBHurtado\XChange\Models\ClaimValidationDecision;

final readonly class ClaimExecutionPreflight
{
    public function __construct(
        private RedemptionContractEngine $contracts,
        private ClaimEvidenceRequirements $requirements,
        private ClaimValidationDecisionJournal $journal,
    ) {}

    /** @param array<string, mixed> $payload */
    public function assertExecutable(Voucher $voucher, array $payload): ClaimValidationDecision
    {
        $rawInstructions = $voucher->getRawOriginal('instructions')
            ?: data_get($voucher->metadata, 'instructions');
        if (is_string($rawInstructions)) {
            $rawInstructions = json_decode($rawInstructions, true);
        } elseif ($rawInstructions instanceof Arrayable) {
            $rawInstructions = $rawInstructions->toArray();
        }

        if (! is_array($rawInstructions) || $rawInstructions === []) {
            $this->journal->record($voucher, $payload, 'blocked', [[
                'field' => 'instructions',
                'code' => 'instructions_missing',
                'severity' => 'block',
            ]]);

            throw new IncompleteClaimEvidence(['instructions']);
        }

        $inputs = (array) data_get($payload, 'inputs', []);
        $inputs['mobile'] ??= data_get($payload, 'mobile');
        $result = $this->contracts->validateInputs($voucher, $inputs);
        $issues = collect($result->issues)
            ->map(static fn ($issue): array => [
                'field' => $issue->field,
                'code' => $issue->code->value,
                'severity' => $issue->severity->value,
            ])
            ->values()
            ->all();

        try {
            $this->requirements->assertComplete($voucher, $payload);
        } catch (IncompleteClaimEvidence $exception) {
            foreach ($exception->missingRequirements as $requirement) {
                $issues[] = [
                    'field' => $requirement,
                    'code' => 'required_evidence_incomplete',
                    'severity' => 'block',
                ];
            }

            $this->journal->record($voucher, $payload, 'blocked', $issues);

            throw $exception;
        }

        $status = $result->should_block
            ? 'blocked'
            : ($result->passed ? 'passed' : 'warned');
        $decision = $this->journal->record($voucher, $payload, $status, $issues);

        if ($result->should_block) {
            throw new VoucherRedemptionContractViolationException(
                violations: collect($issues)
                    ->where('severity', 'block')
                    ->pluck('code', 'field')
                    ->all(),
            );
        }

        return $decision;
    }
}
