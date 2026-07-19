<?php

declare(strict_types=1);

use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryAttemptData;
use LBHurtado\XFeedback\Data\FeedbackProviderReceiptData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;

it('hydrates distribution workspace channels with real x-feedback read-only delivery summaries', function () {
    actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(125.00));

    app(FeedbackDeliveryAttemptRecorderContract::class)->record(new FeedbackDeliveryAttemptData(
        intent_key: 'claim.link.distributed',
        receipts: [
            new FeedbackProviderReceiptData(
                intent_key: 'claim.link.distributed',
                channel: 'sms',
                recipient: new FeedbackRecipientData(
                    type: 'claimant',
                    id: 'claimant-1',
                    email: 'recipient@example.test',
                    phone: '+639171234567',
                    routes: [
                        'sms' => '+639171234567',
                        'webhook' => 'https://example.test/unsafe-webhook',
                    ],
                ),
                status: 'delivered',
                provider_message_id: 'provider-message-secret',
                provider_status: 'DELIVERED',
                provider_payload: [
                    'provider' => 'sms-provider',
                    'token' => 'must-not-render',
                ],
                correlation_id: $voucher->code,
                causation_id: 'feedback-run-distribution-workspace',
                occurred_at: '2026-07-19T14:30:00+08:00',
                meta: [
                    'max_attempts' => 3,
                ],
            ),
        ],
        correlation_id: $voucher->code,
        causation_id: 'feedback-run-distribution-workspace',
    ));

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.distribution', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/DistributionWorkspace')
        ->assertJsonPath('props.distribution_workspace_read_model.status', 'available')
        ->assertJsonPath('props.distribution_workspace_read_model.authorized', true)
        ->assertJsonPath('props.distribution_workspace_read_model.code', $voucher->code)
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.label', 'SMS')
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.status', 'delivered')
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.source', 'x-feedback')
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.metadata.provider_status', 'DELIVERED')
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.metadata.communication_state_only', true)
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.metadata.sends_feedback', false)
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.metadata.retries_delivery', false)
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.metadata.calls_providers', false)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.delivery_count', 1)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.sends_feedback', false)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.retries_delivery', false)
        ->assertJsonMissingPath('props.distribution_workspace_read_model.channels.0.recipient')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.channels.0.provider_message_id')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.channels.0.provider_payload')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.channels.0.idempotency_key');

    $content = $response->getContent();

    expect($content)
        ->not->toContain('recipient@example.test')
        ->not->toContain('+639171234567')
        ->not->toContain('provider-message-secret')
        ->not->toContain('must-not-render')
        ->not->toContain('unsafe-webhook');
});
