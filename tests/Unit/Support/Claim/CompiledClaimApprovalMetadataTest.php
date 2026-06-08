<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\CompiledClaimApprovalMetadata;

it('normalizes empty approval metadata', function () {
    expect(CompiledClaimApprovalMetadata::normalize([]))->toBe([
        'provider' => null,
        'authorization_type' => null,
        'reference_id' => null,
        'expires_at' => null,
        'otp_required' => false,
        'polling_required' => false,
        'manual_review' => false,
        'message' => null,
    ]);
});

it('normalizes approval metadata fields', function () {
    expect(CompiledClaimApprovalMetadata::normalize([
        'provider' => 'payanamics',
        'authorization_type' => 'otp',
        'reference_id' => 'AUTH-123',
        'expires_at' => '2026-06-08T12:00:00+08:00',
        'otp_required' => true,
        'polling_required' => true,
        'manual_review' => false,
        'message' => 'Enter the OTP sent to your mobile number.',
    ]))->toBe([
        'provider' => 'payanamics',
        'authorization_type' => 'otp',
        'reference_id' => 'AUTH-123',
        'expires_at' => '2026-06-08T12:00:00+08:00',
        'otp_required' => true,
        'polling_required' => true,
        'manual_review' => false,
        'message' => 'Enter the OTP sent to your mobile number.',
    ]);
});

it('coerces scalar values safely', function () {
    expect(CompiledClaimApprovalMetadata::normalize([
        'provider' => 123,
        'authorization_type' => 'otp',
        'reference_id' => 456,
        'expires_at' => '',
        'otp_required' => 1,
        'polling_required' => 0,
        'manual_review' => '1',
        'message' => '',
    ]))->toBe([
        'provider' => '123',
        'authorization_type' => 'otp',
        'reference_id' => '456',
        'expires_at' => null,
        'otp_required' => true,
        'polling_required' => false,
        'manual_review' => true,
        'message' => null,
    ]);
});

it('extracts approval metadata from result object', function () {
    $result = (object) [
        'approval_metadata' => [
            'provider' => 'payanamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'otp_required' => true,
        ],
    ];

    expect(CompiledClaimApprovalMetadata::fromResult($result))->toMatchArray([
        'provider' => 'payanamics',
        'authorization_type' => 'otp',
        'reference_id' => 'AUTH-123',
        'otp_required' => true,
    ]);
});

it('falls back to empty metadata when result metadata is invalid', function () {
    $result = (object) [
        'approval_metadata' => 'not-an-array',
    ];

    expect(CompiledClaimApprovalMetadata::fromResult($result))->toBe([
        'provider' => null,
        'authorization_type' => null,
        'reference_id' => null,
        'expires_at' => null,
        'otp_required' => false,
        'polling_required' => false,
        'manual_review' => false,
        'message' => null,
    ]);
});
