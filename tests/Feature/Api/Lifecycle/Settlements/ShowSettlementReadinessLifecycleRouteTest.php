<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

it('shows settlement readiness through the lifecycle route surface', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'settlement',
        'target_amount' => 100.00,
        'metadata' => [
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
        ],
    ]));

    $voucher->forceFill([
        'code' => 'SETTLE123',
        'metadata' => [
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
        ],
    ])->save();

    app()->bind(SettlementEnvelopeReadinessContract::class, fn () => new class implements SettlementEnvelopeReadinessContract {
        public function check(mixed $voucher, string $gate = 'settleable', array $context = []): SettlementEnvelopeReadinessData
        {
            return $this->evaluate($voucher, $gate, $context);
        }

        public function evaluate(mixed $voucher, string $gate = 'settleable', array $context = []): SettlementEnvelopeReadinessData
        {
            return new SettlementEnvelopeReadinessData(
                required: true,
                exists: true,
                ready: true,
                driver: $context['driver'] ?? 'philhealth-bst',
                gate: $gate,
                satisfied: ['patient_payload_present', 'amount_verified'],
                missing: [],
                failed: [],
                warnings: [],
                checklist: [
                    'patient_payload_present' => true,
                    'amount_verified' => true,
                ],
                payload: [
                    'patient_name' => 'Juan Dela Cruz',
                ],
                documents: [
                    'soa' => 'soa.pdf',
                ],
                meta: [
                    'source' => 'test',
                ],
            );
        }
    });

    $response = $this->getJson(
        xchangeApi('vouchers/code/SETTLE123/settlement/readiness')
    );

    $response->assertOk();

    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.voucher_code', 'SETTLE123');
    $response->assertJsonPath('data.required', true);
    $response->assertJsonPath('data.exists', true);
    $response->assertJsonPath('data.ready', true);
    $response->assertJsonPath('data.driver', 'philhealth-bst');
    $response->assertJsonPath('data.gate', 'settleable');
    $response->assertJsonPath('data.satisfied.0', 'patient_payload_present');
    $response->assertJsonPath('data.satisfied.1', 'amount_verified');
    $response->assertJsonPath('data.missing', []);
    $response->assertJsonPath('data.failed', []);
    $response->assertJsonPath('data.warnings', []);
    $response->assertJsonPath('data.checklist.patient_payload_present', true);
    $response->assertJsonPath('data.checklist.amount_verified', true);
    $response->assertJsonPath('data.payload.patient_name', 'Juan Dela Cruz');
    $response->assertJsonPath('data.documents.soa', 'soa.pdf');
    $response->assertJsonPath('data.meta.source', 'test');
});

it('passes custom gate and driver query parameters to settlement readiness evaluation', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'settlement',
        'target_amount' => 100.00,
        'metadata' => [
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
        ],
    ]));

    $voucher->forceFill([
        'code' => 'SETTLE456',
        'metadata' => [
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
        ],
    ])->save();

    app()->bind(SettlementEnvelopeReadinessContract::class, fn () => new class implements SettlementEnvelopeReadinessContract {
        public function check(mixed $voucher, string $gate = 'settleable', array $context = []): SettlementEnvelopeReadinessData
        {
            return $this->evaluate($voucher, $gate, $context);
        }

        public function evaluate(mixed $voucher, string $gate = 'settleable', array $context = []): SettlementEnvelopeReadinessData
        {
            return new SettlementEnvelopeReadinessData(
                required: true,
                exists: true,
                ready: false,
                driver: $context['driver'] ?? 'unknown',
                gate: $gate,
                satisfied: [],
                missing: ['amount_verified'],
                failed: [],
                warnings: [],
                checklist: [],
                payload: [],
                documents: [],
                meta: [],
            );
        }
    });

    $response = $this->getJson(
        xchangeApi('vouchers/code/SETTLE456/settlement/readiness?gate=collectible&driver=custom-driver')
    );

    $response->assertOk();

    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.voucher_code', 'SETTLE456');
    $response->assertJsonPath('data.ready', false);
    $response->assertJsonPath('data.driver', 'custom-driver');
    $response->assertJsonPath('data.gate', 'collectible');
    $response->assertJsonPath('data.missing.0', 'amount_verified');
});

it('returns not found when settlement voucher code does not exist', function () {
    $response = $this->getJson(
        xchangeApi('vouchers/code/UNKNOWN/settlement/readiness')
    );

    $response->assertNotFound();
});
