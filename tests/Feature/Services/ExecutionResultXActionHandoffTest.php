<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Services\Execution\NullExecutionResultActionHandoff;
use LBHurtado\XChange\Services\Execution\XActionExecutionResultActionHandoff;
use Propaganistas\LaravelPhone\PhoneNumber;

it('keeps execution result action handoff null by default', function () {
    expect(app(ExecutionResultActionHandoffContract::class))
        ->toBeInstanceOf(NullExecutionResultActionHandoff::class);
});

it('resolves the x-action execution result handoff from configuration', function () {
    config()->set('x-change.execution_result_handoffs.action', 'x-action');
    app()->forgetInstance(ExecutionResultActionHandoffContract::class);

    expect(app(ExecutionResultActionHandoffContract::class))
        ->toBeInstanceOf(XActionExecutionResultActionHandoff::class);
});

it('composes x-action continuation hints for execution results without executing actions', function () {
    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'execution.result.recorded',
        new ExecutionResultXActionHandoffTestWorkflowAction,
    );

    $result = app(XActionExecutionResultActionHandoff::class)->handoff(
        result: executionResultXActionHandoffResult(),
        context: executionResultXActionHandoffContext(),
    );

    expect($result->target)->toBe('action')
        ->and($result->status)->toBe('composed')
        ->and($result->execution_id)->toBe('exec-x-action-001')
        ->and($result->voucher_code)->toBe('PC-XACTION-HANDOFF')
        ->and($result->correlation_id)->toBe('corr-x-action-handoff')
        ->and($result->blocking)->toBeFalse()
        ->and($result->performed_side_effect)->toBeFalse()
        ->and($result->source)->toBe('x-action-execution-result-action-handoff')
        ->and($result->metadata['event_or_state'])->toBe('execution.result.recorded')
        ->and($result->metadata['actions'])->toHaveCount(1)
        ->and($result->metadata['actions'][0]['key'])->toBe('execution.pay-code.inspect')
        ->and($result->metadata['actions'][0]['label'])->toBe('Inspect Pay Code')
        ->and($result->metadata['actions'][0]['run_id'])->toBeUuid()
        ->and($result->metadata['actions'][0]['target']['redirectable'])->toBeTrue()
        ->and($result->metadata['actions'][0]['target']['url'])->toContain('/x/cockpit/pay-codes/PC-XACTION-HANDOFF')
        ->and($result->metadata['actions'][0]['run_semantics'])->toMatchArray([
            'presentation_run' => true,
            'durable' => false,
            'records_lifecycle' => false,
            'executes_action' => false,
            'authorizes_action' => false,
        ])
        ->and($result->metadata['composition'])->toBe([
            'presentation_only' => true,
            'durable_run' => false,
            'records_lifecycle' => false,
            'executes_action' => false,
            'authorizes_action' => false,
        ])
        ->and($result->metadata)->not->toHaveKey('raw_payload')
        ->and($result->metadata)->not->toHaveKey('provider_payload')
        ->and($result->metadata)->not->toHaveKey('wallet');
});

it('reports a safe no-action result when no x-action rule matches', function () {
    $result = app(XActionExecutionResultActionHandoff::class)->handoff(
        result: executionResultXActionHandoffResult(),
        context: executionResultXActionHandoffContext(),
    );

    expect($result->status)->toBe('no_actions')
        ->and($result->blocking)->toBeFalse()
        ->and($result->performed_side_effect)->toBeFalse()
        ->and($result->metadata['actions'])->toBe([])
        ->and($result->reason)->toBe('x-action composed no continuation actions for this execution result.');
});

it('includes x-action continuation planning in the non-blocking execution result handoff summary', function () {
    config()->set('x-change.execution_result_handoffs.action', 'x-action');
    app()->forgetInstance(ExecutionResultActionHandoffContract::class);
    app()->forgetInstance(ExecutionResultHandoffPipelineContract::class);

    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'execution.result.recorded',
        new ExecutionResultXActionHandoffTestWorkflowAction,
    );

    $summary = app(ExecutionResultHandoffPipelineContract::class)->process(
        result: executionResultXActionHandoffResult(),
        context: executionResultXActionHandoffContext(),
    );

    expect($summary->blocks_execution)->toBeFalse()
        ->and($summary->results['action']->status)->toBe('composed')
        ->and($summary->results['action']->blocking)->toBeFalse()
        ->and($summary->results['action']->performed_side_effect)->toBeFalse()
        ->and($summary->toReportArray()['action']['metadata']['actions'][0]['key'])->toBe('execution.pay-code.inspect');
});

function executionResultXActionHandoffResult(): ExecutionResultData
{
    return new ExecutionResultData(
        execution_id: 'exec-x-action-001',
        successful: true,
        status: 'succeeded',
        driver: 'x_change_live_cash',
        events: [
            ['type' => 'execution.completed'],
        ],
    );
}

function executionResultXActionHandoffContext(): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::fromPhoneNumber(new PhoneNumber('09173011987', 'PH')),
        voucherCode: 'PC-XACTION-HANDOFF',
        correlation: [
            'correlation_id' => 'corr-x-action-handoff',
        ],
    );
}

class ExecutionResultXActionHandoffTestWorkflowAction implements WorkflowActionContract
{
    public function key(): string
    {
        return 'execution.pay-code.inspect';
    }

    public function supports(ActionSubjectData $subject, ActionContextData $context): bool
    {
        return $subject->type === 'execution_result'
            && $context->feature_profile === 'execution'
            && $context->hasCapability('cockpit.pay-code.open')
            && $subject->get('attributes.voucher_code') !== null;
    }

    public function toActionData(ActionSubjectData $subject, ActionContextData $context): ActionData
    {
        return new ActionData(
            key: $this->key(),
            label: 'Inspect Pay Code',
            target: new ActionTargetData(
                type: ActionTargetData::TypeRoute,
                route: 'x-change.cockpit.pay-codes.show',
                parameters: ['code' => (string) $subject->get('attributes.voucher_code')],
            ),
            intent: 'inspect',
            description: 'Open the Pay Code detail screen for read-only execution follow-up.',
            surface: 'cockpit',
            permissions: ['cockpit.pay-code.open'],
            meta: [
                'read_only' => true,
                'executes_action' => false,
            ],
        );
    }
}
