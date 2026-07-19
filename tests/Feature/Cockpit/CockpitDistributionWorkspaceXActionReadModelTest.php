<?php

declare(strict_types=1);

use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;

it('hydrates distribution workspace with real x-action read-only follow-up cta summaries', function () {
    actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(125.00));

    app(ActionRegistryContract::class)->register(
        'cockpit.voucher.view',
        new CockpitDistributionWorkspaceFollowUpWorkflowAction(
            key: 'distribution.manual-review',
            label: 'Review Manual Distribution',
            description: 'Inspect manual distribution readiness before sending externally.',
            target: new ActionTargetData(
                type: ActionTargetData::TypeRoute,
                route: 'x-change.cockpit.pay-codes.distribution',
                parameters: ['code' => $voucher->code],
                payload: [
                    'raw_payload' => 'must-not-render',
                    'provider_payload' => 'must-not-render',
                    'secret' => 'must-not-render',
                ],
            ),
        ),
    );

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.distribution', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/DistributionWorkspace')
        ->assertJsonPath('props.distribution_workspace_read_model.code', $voucher->code)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.key', 'distribution.manual-review')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.label', 'Review Manual Distribution')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.status', 'available')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.source', 'x-action')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.read_only', true)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.available', true)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.target_type', 'route')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.target_route', 'x-change.cockpit.pay-codes.distribution')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.redirectable', true)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.presentation_run', true)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.durable_run', false)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.executes_action', false)
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.metadata.authorizes_action', false)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.action_execution_enabled', false)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.action_payloads_exposed', false)
        ->assertJsonMissingPath('props.distribution_workspace_read_model.actions.0.target.parameters')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.actions.0.target.url');

    expect($response->getContent())
        ->not->toContain('must-not-render')
        ->not->toContain('action_run_id')
        ->not->toContain('run_id');
});

final class CockpitDistributionWorkspaceFollowUpWorkflowAction implements WorkflowActionContract
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $description,
        private readonly ActionTargetData $target,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function supports(ActionSubjectData $subject, ActionContextData $context): bool
    {
        return $subject->type === 'voucher'
            && $context->surface === 'cockpit'
            && in_array('cockpit.view', $context->capabilities, true);
    }

    public function toActionData(ActionSubjectData $subject, ActionContextData $context): ActionData
    {
        return new ActionData(
            key: $this->key,
            label: $this->label,
            target: $this->target,
            intent: 'inspect',
            description: $this->description,
            style: 'secondary',
            audience: 'operator',
            surface: 'cockpit',
        );
    }
}
