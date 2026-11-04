<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Closure;
use LBHurtado\EmiCore\Contracts\DeferredPayoutApprovalScope;
use RuntimeException;

final class UseDeferredPaynamicsOtpResolver
{
    public function run(Closure $callback): mixed
    {
        $scope = $this->scope();
        if ($scope === null) {
            throw new RuntimeException('A deferred payout approval scope is required for the provider.');
        }

        return $scope->run($callback);
    }

    public function isActiveProvider(): bool
    {
        return $this->scope()?->isActiveProvider() ?? false;
    }

    public function requiresInteractiveApproval(): bool
    {
        return $this->scope()?->requiresInteractiveApproval() ?? false;
    }

    private function scope(): ?DeferredPayoutApprovalScope
    {
        $scopes = collect(app()->tagged('emi.deferred-payout-approval-scopes'))
            ->filter(fn (mixed $scope): bool => $scope instanceof DeferredPayoutApprovalScope);
        $active = $scopes->filter(fn (DeferredPayoutApprovalScope $scope): bool => $scope->isActiveProvider());

        if ($active->isNotEmpty()) {
            $scopes = $active;
        }

        if ($scopes->count() > 1) {
            throw new RuntimeException('A unique deferred payout approval scope is required for the provider.');
        }

        return $scopes->first();
    }
}
