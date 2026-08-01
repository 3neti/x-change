<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
use Throwable;

final readonly class SystemPrincipalAccountReadinessInspector
{
    public function __construct(
        private SystemPrincipalProvisioningService $principals,
    ) {}

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    public function inspect(): array
    {
        try {
            $inspection = $this->principals->inspect();
            $principal = $inspection->status === 'existing'
                ? $inspection->model::query()->find($inspection->key)
                : null;
            $authorizationReference = $principal instanceof Model
                ? data_get($principal->getAttribute('onboarding_meta'), 'system_principal.authorization_reference')
                : null;
            $nonInteractive = $principal instanceof Model
                && data_get($principal->getAttribute('onboarding_meta'), 'system_principal.interactive_login') === false;
            $accountReady = $principal instanceof Model
                && method_exists($principal, 'wallet')
                && $principal->wallet()
                    ->where('slug', (string) config('x-change.payout.system_wallet_slug', 'platform'))
                    ->exists();
            $passed = $principal instanceof Authenticatable
                && $principal->exists
                && filled($authorizationReference)
                && $nonInteractive
                && $accountReady;

            return [
                'name' => 'system principal account',
                'passed' => $passed,
                'message' => $passed
                    ? 'persisted non-interactive system principal and Account are ready'
                    : 'provision the configured non-interactive system principal and its Account',
                'meta' => [
                    'principal_persisted' => $principal instanceof Model && $principal->exists,
                    'system_designation_present' => filled($authorizationReference) && $nonInteractive,
                    'account_ready' => $accountReady,
                ],
            ];
        } catch (Throwable) {
            return [
                'name' => 'system principal account',
                'passed' => false,
                'message' => 'provision the configured non-interactive system principal and its Account',
                'meta' => [
                    'principal_persisted' => false,
                    'system_designation_present' => false,
                    'account_ready' => false,
                ],
            ];
        }
    }
}
