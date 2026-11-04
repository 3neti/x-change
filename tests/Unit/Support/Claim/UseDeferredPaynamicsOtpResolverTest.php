<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\DeferredPayoutApprovalScope;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;

it('temporarily resolves the deferred Paynamics OTP resolver', function () {
    app()->forgetInstance(ConstellationOtpResolver::class);

    $result = app(UseDeferredPaynamicsOtpResolver::class)->run(function () {
        return app(ConstellationOtpResolver::class)::class;
    });

    expect($result)->toBe(DeferredOtpResolver::class);
});

it('restores the previous Paynamics OTP resolver after callback', function () {
    $original = new class implements ConstellationOtpResolver
    {
        public function resolve(array $otpRequestPayload): string
        {
            return 'original';
        }
    };

    app()->instance(ConstellationOtpResolver::class, $original);

    $during = app(UseDeferredPaynamicsOtpResolver::class)->run(function () {
        return app(ConstellationOtpResolver::class)::class;
    });

    expect($during)->toBe(DeferredOtpResolver::class)
        ->and(app(ConstellationOtpResolver::class))->toBe($original);
});

it('restores the previous resolver when a deferred callback fails', function () {
    $original = new class implements ConstellationOtpResolver
    {
        public function resolve(array $otpRequestPayload): string
        {
            return 'original';
        }
    };
    app()->instance(ConstellationOtpResolver::class, $original);

    expect(fn () => app(UseDeferredPaynamicsOtpResolver::class)->run(
        fn () => throw new RuntimeException('callback failed'),
    ))->toThrow(RuntimeException::class, 'callback failed');

    expect(app(ConstellationOtpResolver::class))->toBe($original);
});

it('uses the active provider approval scope without relying on its name', function (): void {
    $scope = new class implements DeferredPayoutApprovalScope
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
            return 'other:'.$callback();
        }
    };
    app()->instance('test.other-approval-scope', $scope);
    app()->tag('test.other-approval-scope', 'emi.deferred-payout-approval-scopes');

    $resolver = app(UseDeferredPaynamicsOtpResolver::class);
    expect($resolver->run(fn () => 'claim'))->toBe('other:claim')
        ->and($resolver->isActiveProvider())->toBeTrue()
        ->and($resolver->requiresInteractiveApproval())->toBeTrue();
});
