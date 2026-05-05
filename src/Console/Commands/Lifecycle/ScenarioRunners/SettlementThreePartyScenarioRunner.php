<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Actions\Settlement\SubmitSettlementAttestation;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

final class SettlementThreePartyScenarioRunner implements ScenarioRunnerContract
{
    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $scenarioKey = $context->scenarioKey;
        $scenario = $context->scenario;
        $issuer = $context->issuer;
        $generated = $context->generated;
        $voucher = $context->voucher;
        $estimate = $context->estimate;
        $readiness = $context->readiness ?? app(SettlementEnvelopeReadinessContract::class);

        $phases = [];

        $phases['issue'] = [
            'role' => 'hospital',
            'status' => 'issued',
            'message' => 'Hospital issued settlement voucher.',
            'voucher_code' => $voucher->code,
            'amount' => data_get($scenario, 'amount'),
            'currency' => data_get($scenario, 'currency', 'PHP'),
            'hospital' => data_get($scenario, 'hospital', []),
            'evaluation' => [
                'passed' => true,
                'summary' => 'Hospital issuance recorded.',
            ],
        ];

        $attestationPayload = (array) data_get($scenario, 'phases.attest.payload', []);

        try {
            $attestationResult = app(SubmitSettlementAttestation::class)
                ->handle($voucher, $attestationPayload);

            $voucher = $voucher->refresh();

            $phases['attest'] = [
                'role' => 'patient',
                'status' => 'attested',
                'message' => 'Patient attested care receipt. No funds were disbursed to patient.',
                'payload' => $attestationPayload,
                'claim' => $attestationResult->toArray(),
                'claim_type' => $attestationResult->claim_type,
                'disbursement' => false,
                'patient' => data_get($scenario, 'patient', []),
                'settlement_envelope' => data_get($voucher->metadata ?? [], 'settlement_envelope', []),
                'evaluation' => [
                    'passed' => true,
                    'summary' => 'Patient attestation persisted into settlement envelope metadata.',
                ],
            ];
        } catch (\Throwable $exception) {
            $phases['attest'] = [
                'role' => 'patient',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'payload' => $attestationPayload,
                'claim_type' => 'redeem',
                'disbursement' => false,
                'patient' => data_get($scenario, 'patient', []),
                'error' => [
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
                'evaluation' => [
                    'passed' => false,
                    'summary' => 'Patient attestation failed.',
                ],
            ];
        }

        $beforeContext = (array) data_get($scenario, 'phases.evaluate_before_completion.settlement', []);
        $persistedEnvelope = (array) data_get($voucher->metadata ?? [], 'settlement_envelope', []);

        $beforeReadiness = $readiness->evaluate(
            voucher: $voucher,
            gate: 'settleable',
            context: [
                'requires_envelope' => true,
                'driver' => data_get($scenario, 'metadata.settlement_driver', 'philhealth-bst'),
                'payload' => [
                    ...(array) data_get($persistedEnvelope, 'payload', []),
                    ...(array) data_get($beforeContext, 'payload', []),
                ],
                'documents' => [
                    ...(array) data_get($persistedEnvelope, 'documents', []),
                    ...(array) data_get($beforeContext, 'documents', []),
                ],
                'checklist' => [
                    ...(array) data_get($persistedEnvelope, 'checklist', []),
                    ...(array) data_get($beforeContext, 'checklist', []),
                ],
            ],
        );

        $phases['evaluate_before_completion'] = [
            'role' => 'system',
            'status' => $beforeReadiness->ready ? 'ready' : 'blocked',
            'message' => $beforeReadiness->ready
                ? 'Settlement envelope is ready.'
                : 'Settlement envelope is not ready.',
            'settlement' => $this->formatSettlementReadiness($beforeReadiness),
            'evaluation' => [
                'passed' => ! $beforeReadiness->ready
                    && in_array('amount_verified', $beforeReadiness->missing, true),
                'summary' => 'Envelope is blocked before amount verification.',
            ],
        ];

        $readyContext = (array) data_get($scenario, 'phases.complete_envelope.settlement', []);

        $readyReadiness = $readiness->evaluate(
            voucher: $voucher,
            gate: 'settleable',
            context: [
                'requires_envelope' => true,
                'driver' => data_get($scenario, 'metadata.settlement_driver', 'philhealth-bst'),
                ...$readyContext,
            ],
        );

        $metadata = is_array($voucher->metadata ?? null)
            ? $voucher->metadata
            : [];

        $existingEnvelope = (array) data_get($metadata, 'settlement_envelope', []);

        $settlementPayload = [
            ...(array) data_get($existingEnvelope, 'payload', []),
            ...(array) data_get($readyContext, 'payload', []),
        ];

        $settlementDocuments = [
            ...(array) data_get($existingEnvelope, 'documents', []),
            ...(array) data_get($readyContext, 'documents', []),
        ];

        $settlementChecklist = [
            ...(array) data_get($existingEnvelope, 'checklist', []),
            ...(array) data_get($readyContext, 'checklist', []),
        ];

        $settlementEnvelope = [
            ...$existingEnvelope,
            'driver' => data_get($scenario, 'metadata.settlement_driver', 'philhealth-bst'),
            'payload' => $settlementPayload,
            'documents' => $settlementDocuments,
            'checklist' => $settlementChecklist,
            'updated_at' => now()->toISOString(),
        ];

        $voucher->forceFill([
            'metadata' => [
                ...$metadata,
                'flow_type' => 'settlement',
                'settlement_driver' => data_get($scenario, 'metadata.settlement_driver', 'philhealth-bst'),
                'settlement_envelope' => $settlementEnvelope,

                'settlement_payload' => $settlementPayload,
                'settlement_documents' => $settlementDocuments,
                'settlement_checklist' => $settlementChecklist,
            ],
        ])->save();

        $voucher = $voucher->refresh();

        $phases['complete_envelope'] = [
            'role' => 'system',
            'status' => $readyReadiness->ready ? 'ready' : 'blocked',
            'message' => $readyReadiness->ready
                ? 'Settlement envelope completed.'
                : 'Settlement envelope remains incomplete.',
            'settlement' => $this->formatSettlementReadiness($readyReadiness),
            'evaluation' => [
                'passed' => $readyReadiness->ready,
                'summary' => $readyReadiness->ready
                    ? 'Envelope became settleable.'
                    : 'Envelope did not become settleable.',
            ],
        ];

        $paymentPayload = (array) data_get($scenario, 'phases.settle.payment', []);

        $phases['settle'] = [
            'role' => 'philhealth',
            'recipient_role' => 'hospital',
            'status' => $readyReadiness->ready ? 'settleable' : 'blocked',
            'message' => $readyReadiness->ready
                ? 'PhilHealth may now pay the hospital using the settlement voucher.'
                : 'PhilHealth payment is blocked because envelope is not ready.',
            'payment' => $paymentPayload,
            'evaluation' => [
                'passed' => $readyReadiness->ready,
                'summary' => 'Settlement payment is allowed after envelope readiness.',
            ],
        ];

        $summary = [
            'passed' => collect($phases)->where('evaluation.passed', true)->count(),
            'failed' => collect($phases)->where('evaluation.passed', false)->count(),
            'total' => count($phases),
        ];

        return new ScenarioRunResult(
            exitCode: $summary['failed'] === 0 ? Command::SUCCESS : Command::FAILURE,
            payload: [
                'scenario' => $scenarioKey,
                'label' => $scenario['label'] ?? $scenarioKey,
                'mode' => 'settlement_three_party_flow',
                'roles' => [
                    'issuer' => 'hospital',
                    'attestor' => 'patient',
                    'payer' => 'philhealth',
                    'recipient' => 'hospital',
                ],
                'issuer' => $this->formatUserSummary($issuer),
                'phases' => $phases,
                'phase_summary' => $summary,
                'estimate' => $estimate,
                'generated' => $generated->toArray(),
            ],
        );
    }

    private function formatSettlementReadiness(SettlementEnvelopeReadinessData $readiness): array
    {
        return [
            'required' => $readiness->required,
            'exists' => $readiness->exists,
            'ready' => $readiness->ready,
            'driver' => $readiness->driver,
            'gate' => $readiness->gate,
            'satisfied' => $readiness->satisfied,
            'missing' => $readiness->missing,
            'failed' => $readiness->failed,
            'warnings' => $readiness->warnings,
            'checklist' => $readiness->checklist,
            'payload' => $readiness->payload,
            'documents' => $readiness->documents,
            'meta' => $readiness->meta,
        ];
    }

    private function formatUserSummary(Model $user): array
    {
        return [
            'id' => $user->getKey(),
            'email' => $user->getAttribute('email'),
            'mobile' => $user instanceof HasMobileChannel ? $user->getMobileChannel() : null,
        ];
    }
}
