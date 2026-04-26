<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Services\ApprovalHandlers\ManualApprovalRequirementHandler;
use LBHurtado\XChange\Services\ApprovalHandlers\OtpApprovalRequirementHandler;
use LBHurtado\XChange\Services\DefaultApprovalWorkflowService;

function fakeWithdrawResultForApprovalWorkflow(
    string $status = 'approval_required',
    array $requirements = ['approval'],
    array $meta = [],
): WithdrawPayCodeResultData {
    return new WithdrawPayCodeResultData(
        voucher_code: 'TEST-1234',
        withdrawn: $status === 'withdrawn',
        status: $status,
        requested_amount: 1500.00,
        disbursed_amount: $status === 'withdrawn' ? 1500.00 : 0.00,
        currency: 'PHP',
        remaining_balance: 1000.00,
        slice_number: null,
        remaining_slices: null,
        slice_mode: null,
        redeemer: [],
        bank_account: [],
        disbursement: [],
        messages: $status === 'approval_required'
            ? ['Withdrawal approval is required.']
            : ['Voucher withdrawal successful.'],
        approval_requirements: $requirements,
        approval_meta: $meta,
    );
}

it('returns not required for successful withdrawal', function () {
    $workflow = new DefaultApprovalWorkflowService;

    $result = $workflow->resolve(
        fakeWithdrawResultForApprovalWorkflow(status: 'withdrawn'),
    );

    expect($result->status)->toBe('not_required')
        ->and($result->next_actions)->toBe([])
        ->and($result->messages)->toContain('No approval workflow is required.');
});

it('returns pending workflow for approval required result', function () {
    $workflow = new DefaultApprovalWorkflowService([
        'approval' => new ManualApprovalRequirementHandler,
    ]);

    $result = $workflow->resolve(
        fakeWithdrawResultForApprovalWorkflow(
            requirements: ['approval'],
            meta: [
                'source' => 'threshold',
                'threshold' => 1000.00,
                'amount' => 1500.00,
            ],
        ),
    );

    expect($result->status)->toBe('pending')
        ->and($result->requirements)->toBe(['approval'])
        ->and($result->meta['source'])->toBe('threshold')
        ->and($result->next_actions)->toHaveCount(1);
});

it('maps approval requirement to manual approval action', function () {
    $workflow = new DefaultApprovalWorkflowService([
        'approval' => new ManualApprovalRequirementHandler,
    ]);

    $result = $workflow->resolve(
        fakeWithdrawResultForApprovalWorkflow(requirements: ['approval']),
    );

    expect($result->next_actions[0]['type'])->toBe('approval')
        ->and($result->next_actions[0]['status'])->toBe('pending')
        ->and($result->next_actions[0]['label'])->toBe('Manual approval required');
});

it('maps otp requirement to otp action', function () {
    $workflow = new DefaultApprovalWorkflowService([
        'otp' => new OtpApprovalRequirementHandler,
    ]);

    $result = $workflow->resolve(
        fakeWithdrawResultForApprovalWorkflow(
            requirements: ['otp'],
            meta: [
                'channel' => 'sms',
                'masked_mobile' => '0917***1987',
            ],
        ),
    );

    expect($result->next_actions[0]['type'])->toBe('otp')
        ->and($result->next_actions[0]['status'])->toBe('challenge_required')
        ->and($result->next_actions[0]['label'])->toBe('OTP verification required')
        ->and($result->next_actions[0]['meta']['channel'])->toBe('sms')
        ->and($result->next_actions[0]['meta']['masked_mobile'])->toBe('0917***1987');
});

it('marks unknown requirement as unsupported', function () {
    $workflow = new DefaultApprovalWorkflowService;

    $result = $workflow->resolve(
        fakeWithdrawResultForApprovalWorkflow(requirements: ['biometric']),
    );

    expect($result->next_actions[0]['type'])->toBe('biometric')
        ->and($result->next_actions[0]['status'])->toBe('unsupported')
        ->and($result->next_actions[0]['message'])
        ->toBe('No approval workflow handler is configured for [biometric].');
});

it('preserves approval meta into workflow result', function () {
    $workflow = new DefaultApprovalWorkflowService([
        'approval' => new ManualApprovalRequirementHandler,
    ]);

    $meta = [
        'source' => 'threshold',
        'threshold' => 1000.00,
        'amount' => 1500.00,
    ];

    $result = $workflow->resolve(
        fakeWithdrawResultForApprovalWorkflow(
            requirements: ['approval'],
            meta: $meta,
        ),
    );

    expect($result->meta)->toBe($meta)
        ->and($result->next_actions[0]['meta'])->toBe($meta);
});
