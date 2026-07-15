<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'x-change.execution_result_handoffs.action' => 'x-action',
        'queue.default' => 'sync',
    ]);

    app()->forgetInstance(ExecutionResultActionHandoffContract::class);

    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'execution.result.recorded',
        new LifecycleExecutionResultXActionHandoffWorkflowAction,
    );

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('reports x-action continuation planning in lifecycle scenario execution without executing actions', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and(data_get($json, 'execution.handoffs.blocks_execution'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.action.status'))->toBe('composed')
        ->and(data_get($json, 'execution.handoffs.action.performed_side_effect'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.action.metadata.event_or_state'))->toBe('execution.result.recorded')
        ->and(data_get($json, 'execution.handoffs.action.metadata.composition.executes_action'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.action.metadata.composition.authorizes_action'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.action.metadata.actions.0.key'))->toBe('execution.pay-code.inspect')
        ->and(data_get($json, 'execution.handoffs.action.metadata.actions.0.target.redirectable'))->toBeTrue()
        ->and(data_get($json, 'execution.handoffs.action.metadata.actions.0.run_semantics.executes_action'))->toBeFalse();
});

class LifecycleExecutionResultXActionHandoffWorkflowAction implements WorkflowActionContract
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
