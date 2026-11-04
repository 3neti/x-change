<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\DeferredPayoutApprovalScope;
use LBHurtado\EmiCore\Contracts\PendingPayoutOtpInterpreter;
use LBHurtado\EmiCore\Contracts\PendingPayoutOtpStore;
use LBHurtado\EmiPaynamicsConstellation\ConstellationServiceProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;

it('implements the provider-neutral pending payout OTP store contract', function () {
    expect(app(ClaimApprovalPendingOtpStore::class))
        ->toBeInstanceOf(PendingPayoutOtpStore::class);
});

it('stores pending OTP metadata by Paynamics request id', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    $store->putPendingOtp([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ]);

    expect($store->pending('TEST-Z3EL-09173011987-S1'))->toMatchArray([
        'provider' => 'paynamics',
        'authorization_type' => 'otp',
        'reference_id' => 'TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'target' => 'OTP successfully sent to 639171234567',
        'otp_required' => true,
        'polling_required' => false,
        'manual_review' => false,
        'message' => 'Paynamics payout OTP is pending.',
    ]);
});

it('returns submitted OTP by Paynamics request id', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    $store->putSubmittedOtp('TEST-Z3EL-09173011987-S1', ' 441498 ');

    expect($store->getSubmittedOtp([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
    ]))->toBe('441498');
});

it('returns null when no submitted OTP exists', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    expect($store->getSubmittedOtp([
        'request_id' => 'TEST-MISSING',
    ]))->toBeNull();
});

it('forgets pending and submitted OTP state by Paynamics request id', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    $store->putPendingOtp([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ]);
    $store->putSubmittedOtp('TEST-Z3EL-09173011987-S1', '441498');

    $store->forget('TEST-Z3EL-09173011987-S1');

    expect($store->pending('TEST-Z3EL-09173011987-S1'))->toBeNull()
        ->and($store->getSubmittedOtp([
            'request_id' => 'TEST-Z3EL-09173011987-S1',
        ]))->toBeNull();
});

it('binds the pending OTP store contract', function () {
    expect(app(PendingPayoutOtpStore::class))
        ->toBeInstanceOf(ClaimApprovalPendingOtpStore::class);
});

it('fails closed when provider OTP interpretation is ambiguous', function (): void {
    app()->instance('test.other-otp-interpreter', new class implements PendingPayoutOtpInterpreter
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function reference(array $request): ?string
        {
            return 'other-reference';
        }

        public function pendingMetadata(array $request, array $result): array
        {
            return [];
        }
    });
    app()->tag('test.other-otp-interpreter', 'emi.pending-payout-otp-interpreters');

    expect(fn () => app(ClaimApprovalPendingOtpStore::class)->getSubmittedOtp(['request_id' => 'r1']))
        ->toThrow(RuntimeException::class, 'unique pending payout OTP interpreter');
});

it('selects the active provider OTP interpreter', function (): void {
    app()->instance('test.active-otp-interpreter', new class implements PendingPayoutOtpInterpreter
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function reference(array $request): ?string
        {
            return $request['other_ref'] ?? null;
        }

        public function pendingMetadata(array $request, array $result): array
        {
            return ['provider' => 'other'];
        }
    });
    app()->tag('test.active-otp-interpreter', 'emi.pending-payout-otp-interpreters');
    app()->instance('test.active-otp-scope', new class implements DeferredPayoutApprovalScope
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function isActiveProvider(): bool
        {
            return true;
        }

        public function requiresInteractiveApproval(): bool
        {
            return true;
        }

        public function run(Closure $callback): mixed
        {
            return $callback();
        }
    });
    app()->tag('test.active-otp-scope', 'emi.deferred-payout-approval-scopes');

    $store = app(ClaimApprovalPendingOtpStore::class);
    $store->putPendingOtp(['other_ref' => 'other-1'], []);
    expect($store->pending('other-1'))->toMatchArray(['provider' => 'other']);
});

it('keeps the provider legacy OTP interface available through the core store', function (): void {
    $this->app->register(ConstellationServiceProvider::class);
    $legacy = app(PendingOtpStore::class);
    $legacy->putPendingOtp(['request_id' => 'LEGACY-1'], ['data' => 'OTP sent']);

    expect($legacy)->toBeInstanceOf(PendingOtpStore::class)
        ->and(app(PendingPayoutOtpStore::class)->pending('LEGACY-1'))->not->toBeNull();
});
