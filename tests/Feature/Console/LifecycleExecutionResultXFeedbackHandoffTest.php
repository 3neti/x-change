<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'x-change.execution_result_handoffs.feedback' => 'x-feedback',
        'queue.default' => 'sync',
    ]);

    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('reports x-feedback delivery planning in lifecycle scenario execution without sending feedback', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and(data_get($json, 'execution.handoffs.blocks_execution'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.feedback.status'))->toBe('planned')
        ->and(data_get($json, 'execution.handoffs.feedback.performed_side_effect'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.intent_key'))->toBe('execution.result.recorded')
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.event_type'))->toBe('execution.result.recorded')
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.delivery_boundary'))->toBe('prepare_only')
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.composition.sends_feedback'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.composition.owns_lifecycle_truth'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.plan_items.0.channel'))->toBe('in_app')
        ->and(data_get($json, 'execution.handoffs.feedback.metadata.plan_items.0.status'))->toBe('planned');
});
