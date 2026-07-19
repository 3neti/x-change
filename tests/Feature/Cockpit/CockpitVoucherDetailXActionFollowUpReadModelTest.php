<?php

declare(strict_types=1);

use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;

it('hydrates voucher detail with real x-action read-only follow-up cta summaries', function () {
    actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(95.00));

    app(ActionRegistryContract::class)->register(
        'cockpit.voucher.view',
        new CockpitVoucherDetailFollowUpWorkflowAction(
            key: 'voucher.distribution.inspect',
            label: 'Inspect Distribution',
            description: 'Open the read-only distribution workspace.',
            target: new ActionTargetData(
                type: ActionTargetData::TypeRoute,
                route: 'x-change.cockpit.pay-codes.distribution',
                parameters: ['code' => $voucher->code],
                payload: [
                    'raw_payload' => 'must-not-render',
                    'wallet' => 'must-not-render',
                ],
            ),
        ),
    );

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/VoucherDetail')
        ->assertJsonPath('props.read_model.code', $voucher->code)
        ->assertJsonPath('props.read_model.actions.status', 'available')
        ->assertJsonPath('props.read_model.actions.authorized', true)
        ->assertJsonPath('props.read_model.actions.redactions.payloads', 'safe-action-host-summary-only')
        ->assertJsonPath('props.read_model.actions.redactions.source', 'x-action')
        ->assertJsonPath('props.read_model.actions.redactions.presentation_only', true)
        ->assertJsonPath('props.read_model.actions.redactions.durable_run', false)
        ->assertJsonPath('props.read_model.actions.redactions.executes_action', false)
        ->assertJsonPath('props.read_model.actions.redactions.authorizes_action', false)
        ->assertJsonPath('props.read_model.actions.redactions.records_lifecycle', false)
        ->assertJsonPath('props.read_model.actions.actions.0.key', 'voucher.distribution.inspect')
        ->assertJsonPath('props.read_model.actions.actions.0.label', 'Inspect Distribution')
        ->assertJsonPath('props.read_model.actions.actions.0.status', 'available')
        ->assertJsonPath('props.read_model.actions.actions.0.description', 'Open the read-only distribution workspace.')
        ->assertJsonPath('props.read_model.actions.actions.0.target.type', 'route')
        ->assertJsonPath('props.read_model.actions.actions.0.target.route', 'x-change.cockpit.pay-codes.distribution')
        ->assertJsonPath('props.read_model.actions.actions.0.target.redirectable', true)
        ->assertJsonPath('props.read_model.actions.actions.0.meta.run_semantics.presentation_run', true)
        ->assertJsonPath('props.read_model.actions.actions.0.meta.run_semantics.durable', false)
        ->assertJsonMissingPath('props.read_model.actions.actions.0.run')
        ->assertJsonMissingPath('props.read_model.actions.actions.0.handoff')
        ->assertJsonMissingPath('props.read_model.actions.actions.0.target.parameters')
        ->assertJsonMissingPath('props.read_model.actions.actions.0.target.url');

    $content = $response->getContent();

    expect($content)
        ->not->toContain('must-not-render')
        ->not->toContain('action_run_id')
        ->not->toContain('run_id')
        ->not->toContain('feedback-run-voucher-detail');
});

final class CockpitVoucherDetailFollowUpWorkflowAction implements WorkflowActionContract
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
