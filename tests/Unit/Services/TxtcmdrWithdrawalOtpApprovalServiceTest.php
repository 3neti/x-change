<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Services\TxtcmdrWithdrawalOtpApprovalService;

it('requests otp through txtcmdr', function () {
    config()->set('x-change.withdrawal.otp.txtcmdr.base_url', 'https://txtcmdr.test');
    config()->set('x-change.withdrawal.otp.txtcmdr.api_token', 'secret-token');
    config()->set('x-change.withdrawal.otp.txtcmdr.sender_id', 'cashless');

    Http::fake([
        'txtcmdr.test/api/otp/request' => Http::response([
            'status' => 'requested',
            'verification_id' => 'ver-123',
        ]),
    ]);

    $result = app(TxtcmdrWithdrawalOtpApprovalService::class)->request(
        mobile: '09173011987',
        reference: 'VOUCHER-123',
        context: ['amount' => 1500],
    );

    expect($result['status'])->toBe('requested')
        ->and($result['verification_id'])->toBe('ver-123');

    Http::assertSent(fn ($request) => $request->url() === 'https://txtcmdr.test/api/otp/request'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['mobile'] === '09173011987'
        && $request['reference'] === 'VOUCHER-123'
        && $request['sender_id'] === 'cashless'
        && $request['context']['amount'] === 1500
    );
});

it('verifies otp through txtcmdr', function () {
    config()->set('x-change.withdrawal.otp.txtcmdr.base_url', 'https://txtcmdr.test');
    config()->set('x-change.withdrawal.otp.txtcmdr.api_token', 'secret-token');

    Http::fake([
        'txtcmdr.test/api/otp/verify' => Http::response([
            'verified' => true,
        ]),
    ]);

    $verified = app(TxtcmdrWithdrawalOtpApprovalService::class)->verify(
        mobile: '09173011987',
        reference: 'VOUCHER-123',
        code: '123456',
        context: ['amount' => 1500],
    );

    expect($verified)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://txtcmdr.test/api/otp/verify'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['mobile'] === '09173011987'
        && $request['reference'] === 'VOUCHER-123'
        && $request['code'] === '123456'
    );
});
