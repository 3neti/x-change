<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Commercial\CommercialBillingPolicy;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use Throwable;

final readonly class CommercialPrincipalAccountReadinessInspector
{
    public function __construct(
        private CommercialBillingPolicy $billing,
        private CommercialPrincipalProvisioningService $principals,
    ) {}

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    public function inspect(): array
    {
        if (! $this->billing->isBillable()) {
            return [
                'name' => 'commercial principal account',
                'passed' => true,
                'message' => 'commercial principal Account is not required in informational billing mode',
                'meta' => [
                    'required' => false,
                    'principal_persisted' => false,
                    'commercial_designation_present' => false,
                    'account_ready' => false,
                    'routing_state' => 'not_applicable',
                ],
            ];
        }

        try {
            $inspection = $this->principals->inspect();
            $principal = $inspection->status === 'existing'
                ? CommercialPrincipal::query()->find($inspection->key)
                : null;
            $designationReady = $principal instanceof CommercialPrincipal
                && $principal->active
                && filled($principal->authorization_reference)
                && data_get($principal->metadata, 'interactive_login') === false;
            $accountReady = $principal instanceof CommercialPrincipal
                && $inspection->accountReady;
            $passed = $principal instanceof CommercialPrincipal
                && $principal->exists
                && $designationReady
                && $accountReady;

            return [
                'name' => 'commercial principal account',
                'passed' => $passed,
                'message' => $passed
                    ? 'persisted non-interactive commercial principal and Commercial Revenue Account are ready; Treasury routing remains separately governed'
                    : 'provision the configured commercial principal and its Commercial Revenue Account',
                'meta' => [
                    'required' => true,
                    'principal_persisted' => $principal instanceof CommercialPrincipal
                        && $principal->exists,
                    'commercial_designation_present' => $designationReady,
                    'account_ready' => $accountReady,
                    'routing_state' => 'pending_controlled_migration',
                ],
            ];
        } catch (Throwable) {
            return [
                'name' => 'commercial principal account',
                'passed' => false,
                'message' => 'provision the configured commercial principal and its Commercial Revenue Account',
                'meta' => [
                    'required' => true,
                    'principal_persisted' => false,
                    'commercial_designation_present' => false,
                    'account_ready' => false,
                    'routing_state' => 'pending_controlled_migration',
                ],
            ];
        }
    }
}
