<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Exceptions\IncompleteClaimEvidence;
use LBHurtado\XChange\Models\ClaimValidationDecision;
use LBHurtado\XChange\Services\Claim\ClaimExecutionPreflight;

it('blocks missing required instructions before an execution can begin', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'PREFLIGHT-BLOCK',
        'metadata' => ['instructions' => validVoucherInstructions(overrides: [
            'inputs' => ['fields' => ['signature']],
        ])->toArray()],
        'state' => 'active',
    ]);

    expect(fn () => app(ClaimExecutionPreflight::class)->assertExecutable($voucher, []))
        ->toThrow(IncompleteClaimEvidence::class);

    expect(ClaimValidationDecision::query()->where('voucher_id', $voucher->getKey())->sole()->status)
        ->toBe('blocked');
});

it('blocks a Pay Code with no frozen instructions', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'PREFLIGHT-NO-INSTRUCTIONS',
        'metadata' => [],
        'state' => 'active',
    ]);

    expect(fn () => app(ClaimExecutionPreflight::class)->assertExecutable($voucher, []))
        ->toThrow(IncompleteClaimEvidence::class, 'instructions');

    expect(ClaimValidationDecision::query()->where('voucher_id', $voucher->getKey())->sole()->status)
        ->toBe('blocked');
});

it('honors a configured warning while recording the validation decision', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'PREFLIGHT-WARN',
        'metadata' => ['instructions' => validVoucherInstructions(overrides: [
            'validation' => [
                'selfie' => ['required' => true, 'on_failure' => 'warn'],
            ],
        ])->toArray()],
        'state' => 'active',
    ]);

    $decision = app(ClaimExecutionPreflight::class)->assertExecutable($voucher, [
        'mobile' => '09173011987',
    ]);

    expect($decision->status)->toBe('warned')
        ->and($decision->issues)->toContain([
            'field' => 'selfie',
            'code' => 'missing',
            'severity' => 'warn',
        ]);
});
