<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderPayoutApprovalReplayPolicy;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;

it('uses a provider replay policy only for an approval replay', function (): void {
    $policy = new class implements ProviderPayoutApprovalReplayPolicy
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function supportsProvider(string $provider): bool
        {
            return $provider === 'other';
        }

        public function shouldResume(array $payload, Closure $metadata, Throwable $exception): bool
        {
            return true;
        }

        public function isDeferredApprovalPending(array $result, Closure $metadata): bool
        {
            return false;
        }

        public function pendingMessage(): string
        {
            return 'Approval pending.';
        }
    };
    app()->instance('test.other-replay-policy', $policy);
    app()->tag('test.other-replay-policy', 'emi.payout-approval-replay-policies');
    $method = new ReflectionMethod(SubmitPayCodeClaim::class, 'shouldReplayApprovedPaynamicsPayout');
    $action = app(SubmitPayCodeClaim::class);
    $voucher = new Voucher;
    $exception = new RuntimeException('test');

    expect($method->invoke($action, $voucher, ['approval' => ['provider' => 'other', 'resume' => true]], $exception))->toBeTrue()
        ->and($method->invoke($action, $voucher, ['approval' => ['provider' => 'other']], $exception))->toBeFalse()
        ->and($method->invoke($action, $voucher, ['approval' => ['provider' => 'unknown', 'resume' => true]], $exception))->toBeFalse();
});
