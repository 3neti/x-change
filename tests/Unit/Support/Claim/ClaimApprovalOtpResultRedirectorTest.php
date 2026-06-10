<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\ClaimApprovalOtpResultRedirector;

it('redirects successful OTP result to claim success page', function () {
    $voucher = issueVoucher();

    $response = app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'success',
    ]);

    expect($response->getTargetUrl())->toBe(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));
});

it('redirects completed OTP result to claim success page', function () {
    $voucher = issueVoucher();

    $response = app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'completed',
    ]);

    expect($response->getTargetUrl())->toBe(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));
});

it('redirects pending OTP result back to approval page', function () {
    $voucher = issueVoucher();

    $response = app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'pending',
    ]);

    expect($response->getTargetUrl())->toBe(route('x-change.claim.approval', [
        'code' => $voucher->code,
    ]));
});

it('redirects received OTP result back to approval page', function () {
    $voucher = issueVoucher();

    $response = app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'received',
    ]);

    expect($response->getTargetUrl())->toBe(route('x-change.claim.approval', [
        'code' => $voucher->code,
    ]));
});

it('redirects failed OTP result back with OTP error', function () {
    $voucher = issueVoucher();

    app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'failed',
        'messages' => ['Invalid OTP.'],
    ]);

    expect(session('errors')?->getBag('default')->first('otp'))
        ->toBe('Invalid OTP.');
});

it('uses default error for failed OTP result without message', function () {
    $voucher = issueVoucher();

    app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'failed',
        'messages' => [],
    ]);

    expect(session('errors')?->getBag('default')->first('otp'))
        ->toBe('Unable to verify OTP.');
});

it('rejects unsupported OTP result status', function () {
    $voucher = issueVoucher();

    app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, [
        'status' => 'unknown',
    ]);
})->throws(RuntimeException::class, 'Unsupported approval OTP result status [unknown].');
