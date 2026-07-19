<?php

declare(strict_types=1);

use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryAttemptData;
use LBHurtado\XFeedback\Data\FeedbackProviderReceiptData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;

it('hydrates voucher detail with real x-feedback read-only delivery summaries', function () {
    actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(75.00));

    app(FeedbackDeliveryAttemptRecorderContract::class)->record(new FeedbackDeliveryAttemptData(
        intent_key: 'claim.succeeded.claimant',
        receipts: [
            new FeedbackProviderReceiptData(
                intent_key: 'claim.succeeded.claimant',
                channel: 'sms',
                recipient: new FeedbackRecipientData(
                    type: 'claimant',
                    id: 'claimant-1',
                    name: 'Sensitive Recipient',
                    email: 'recipient@example.test',
                    phone: '+639171234567',
                    routes: [
                        'sms' => '+639171234567',
                        'webhook' => 'https://example.test/secret-webhook',
                    ],
                ),
                status: 'sent',
                provider_message_id: 'provider-message-secret',
                provider_status: 'ACCEPTED',
                provider_payload: [
                    'provider' => 'sms-provider',
                    'accepted' => true,
                    'token' => 'must-not-render',
                ],
                correlation_id: $voucher->code,
                causation_id: 'feedback-run-voucher-detail',
                occurred_at: '2026-07-19T09:00:00+08:00',
                meta: [
                    'max_attempts' => 3,
                    'expires_at' => '2026-07-20T09:00:00+08:00',
                ],
            ),
        ],
        correlation_id: $voucher->code,
        causation_id: 'feedback-run-voucher-detail',
    ));

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/VoucherDetail')
        ->assertJsonPath('props.read_model.code', $voucher->code)
        ->assertJsonPath('props.read_model.feedback.status', 'available')
        ->assertJsonPath('props.read_model.feedback.authorized', true)
        ->assertJsonPath('props.read_model.feedback.redactions.payloads', 'communication-delivery-summary-only')
        ->assertJsonPath('props.read_model.feedback.redactions.source', 'x-feedback')
        ->assertJsonPath('props.read_model.feedback.redactions.communication_state_only', true)
        ->assertJsonPath('props.read_model.feedback.redactions.audit_truth', false)
        ->assertJsonPath('props.read_model.feedback.redactions.sends_feedback', false)
        ->assertJsonPath('props.read_model.feedback.redactions.retries_delivery', false)
        ->assertJsonPath('props.read_model.feedback.redactions.calls_providers', false)
        ->assertJsonPath('props.read_model.feedback.deliveries.0.intent_key', 'claim.succeeded.claimant')
        ->assertJsonPath('props.read_model.feedback.deliveries.0.channel', 'sms')
        ->assertJsonPath('props.read_model.feedback.deliveries.0.status', 'sent')
        ->assertJsonPath('props.read_model.feedback.deliveries.0.provider_status', 'ACCEPTED')
        ->assertJsonPath('props.read_model.feedback.deliveries.0.correlation_id', $voucher->code)
        ->assertJsonMissingPath('props.read_model.feedback.deliveries.0.recipient')
        ->assertJsonMissingPath('props.read_model.feedback.deliveries.0.provider_message_id')
        ->assertJsonMissingPath('props.read_model.feedback.deliveries.0.provider_payload')
        ->assertJsonMissingPath('props.read_model.feedback.deliveries.0.idempotency_key');

    $content = $response->getContent();

    expect($content)
        ->not->toContain('Sensitive Recipient')
        ->not->toContain('recipient@example.test')
        ->not->toContain('+639171234567')
        ->not->toContain('provider-message-secret')
        ->not->toContain('must-not-render')
        ->not->toContain('secret-webhook');
});
