<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ApprovalRequirementHandlerContract;
use LBHurtado\XChange\Contracts\ApprovalWorkflowContract;
use LBHurtado\XChange\Data\ApprovalWorkflowResultData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;

/**
 * DefaultApprovalWorkflowService
 *
 * Resolves machine-readable authorization decisions (from cash) into actionable
 * approval workflows for clients (UI, API consumers, vendor systems).
 *
 * -------------------------------------------------------------------------
 * 🧠 Purpose
 * -------------------------------------------------------------------------
 *
 * This service acts as the bridge between:
 *
 *   1. Cash domain decisions (approval_required, requirements, meta)
 *   2. Client-facing workflow actions (OTP, manual approval, biometric, etc.)
 *
 * It transforms a withdrawal result into a structured workflow response:
 *
 *   WithdrawPayCodeResultData  →  ApprovalWorkflowResultData
 *
 * -------------------------------------------------------------------------
 * 🔁 Input
 * -------------------------------------------------------------------------
 *
 * - WithdrawPayCodeResultData $result
 *
 *   Expected shape (when approval is required):
 *
 *   [
 *     'status' => 'approval_required',
 *     'approval_requirements' => ['approval', 'otp', ...],
 *     'approval_meta' => [
 *         'source' => 'threshold',
 *         'threshold' => 1000,
 *         'amount' => 1500,
 *     ],
 *     'messages' => [...]
 *   ]
 *
 * - array $context (optional)
 *
 *   Additional contextual data passed to handlers:
 *   - voucher_code
 *   - payload
 *   - scenario name (optional)
 *   - any custom runtime data
 *
 * -------------------------------------------------------------------------
 * 📤 Output
 * -------------------------------------------------------------------------
 *
 * Returns ApprovalWorkflowResultData:
 *
 * [
 *   'status' => 'pending' | 'not_required',
 *   'next_actions' => [
 *       [
 *         'type' => 'otp' | 'approval' | ...,
 *         'status' => 'pending' | 'challenge_required' | 'unsupported',
 *         'label' => '...',
 *         'message' => '...',
 *         'meta' => [...]
 *       ],
 *       ...
 *   ],
 *   'requirements' => [...],
 *   'meta' => [...],
 *   'messages' => [...]
 * ]
 *
 * -------------------------------------------------------------------------
 * ⚙️ Behavior
 * -------------------------------------------------------------------------
 *
 * - If status !== 'approval_required':
 *     → returns "not_required"
 *
 * - If status === 'approval_required':
 *     → iterates over approval_requirements
 *     → delegates each requirement to a handler
 *     → builds next_actions array
 *
 * - If a handler is not registered:
 *     → marks requirement as "unsupported"
 *
 * -------------------------------------------------------------------------
 * 🔌 Handlers
 * -------------------------------------------------------------------------
 *
 * Handlers must implement:
 *
 *   ApprovalRequirementHandlerContract
 *
 * Each handler is responsible for translating a requirement into an action.
 *
 * Examples:
 *
 *   'approval' → ManualApprovalRequirementHandler
 *   'otp'      → OtpApprovalRequirementHandler
 *
 * -------------------------------------------------------------------------
 * 🧩 Extensibility
 * -------------------------------------------------------------------------
 *
 * New approval types can be added without modifying this service:
 *
 *   1. Create a handler class
 *   2. Register it in config:
 *
 *      'approval_workflow.handlers' => [
 *          'biometric' => BiometricHandler::class,
 *      ]
 *
 *   3. Cash emits:
 *      approval_requirements = ['biometric']
 *
 * -------------------------------------------------------------------------
 * 🔄 Example Usage
 * -------------------------------------------------------------------------
 *
 * $result = $withdrawalProcessor->process($voucher, $payload);
 *
 * if ($result->status === 'approval_required') {
 *     $workflow = app(ApprovalWorkflowContract::class)->resolve($result, [
 *         'voucher_code' => $result->voucher_code,
 *         'payload' => $payload,
 *     ]);
 *
 *     return [
 *         'withdrawal' => $result,
 *         'approval_workflow' => $workflow,
 *     ];
 * }
 *
 * -------------------------------------------------------------------------
 * 🧠 Design Principles
 * -------------------------------------------------------------------------
 *
 * - Cash decides WHAT is required
 * - x-change decides HOW to fulfill it
 * - Handlers define execution semantics
 *
 * This keeps:
 *   ✔ domain logic (cash) pure
 *   ✔ workflow logic (x-change) flexible
 *   ✔ integrations (OTP, biometrics, etc.) pluggable
 *
 * -------------------------------------------------------------------------
 * 🚀 Future Extensions
 * -------------------------------------------------------------------------
 *
 * - Multi-step workflows (OTP → approval → biometric)
 * - Async approval states
 * - Retry / expiration handling
 * - Approval audit trails
 * - Vendor-specific approval policies
 *
 */
class DefaultApprovalWorkflowService implements ApprovalWorkflowContract
{
    /**
     * @param  array<string, ApprovalRequirementHandlerContract>  $handlers
     */
    public function __construct(
        protected array $handlers = [],
    ) {}

    public function resolve(
        WithdrawPayCodeResultData $result,
        array $context = [],
    ): ApprovalWorkflowResultData {
        if ($result->status !== 'approval_required') {
            return new ApprovalWorkflowResultData(
                status: 'not_required',
                messages: ['No approval workflow is required.'],
            );
        }

        $actions = [];

        foreach ($result->approval_requirements as $requirement) {
            $handler = $this->handlers[$requirement] ?? null;

            if (! $handler) {
                $actions[] = [
                    'type' => $requirement,
                    'status' => 'unsupported',
                    'message' => "No approval workflow handler is configured for [{$requirement}].",
                ];

                continue;
            }

            $actions[] = $handler->handle(
                meta: $result->approval_meta,
                context: $context,
            );
        }

        return new ApprovalWorkflowResultData(
            status: 'pending',
            next_actions: $actions,
            requirements: $result->approval_requirements,
            meta: $result->approval_meta,
            messages: $result->messages,
        );
    }
}
