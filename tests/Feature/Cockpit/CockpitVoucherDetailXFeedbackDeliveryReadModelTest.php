<?php

declare(strict_types=1);

use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryAttemptData;
use LBHurtado\XFeedback\Data\FeedbackProviderReceiptData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

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

it('hydrates only the exact campaign delivery attached to a beneficiary Pay Code', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(20.00));
    $authorizationReference = '01KZ0000000000000000000001';

    foreach (['campaign.pay_code.delivery', 'campaign.pay_code.sibling'] as $intentKey) {
        app(FeedbackDeliveryAttemptRecorderContract::class)->record(new FeedbackDeliveryAttemptData(
            intent_key: $intentKey,
            receipts: [
                new FeedbackProviderReceiptData(
                    intent_key: $intentKey,
                    channel: 'sms',
                    recipient: new FeedbackRecipientData(
                        type: 'campaign_beneficiary',
                        id: $intentKey,
                        routes: ['sms' => '+639171234567'],
                    ),
                    status: 'sent',
                    provider_status: 'ACCEPTED',
                    correlation_id: $authorizationReference,
                    causation_id: 'campaign-delivery-test',
                    occurred_at: '2026-08-03T09:00:00+08:00',
                ),
            ],
            correlation_id: $authorizationReference,
            causation_id: 'campaign-delivery-test',
        ));
    }

    $exactDeliveryId = (string) FeedbackDeliveryRecord::query()
        ->where('intent_key', 'campaign.pay_code.delivery')
        ->valueOrFail('delivery_id');
    $siblingDeliveryId = (string) FeedbackDeliveryRecord::query()
        ->where('intent_key', 'campaign.pay_code.sibling')
        ->valueOrFail('delivery_id');
    $worksheet = CampaignWorksheet::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'profile' => 'payroll',
        'name' => 'Exact delivery isolation',
        'currency' => 'PHP',
        'status' => 'authorized',
    ]);
    $row = $worksheet->rows()->create([
        'ordinal' => 1,
        'beneficiary_ciphertext' => ['mobile' => '09171234567'],
        'amount_minor' => 2_000,
        'currency' => 'PHP',
        'status' => 'issued',
    ]);
    $authorization = $worksheet->authorizations()->create([
        'reference' => $authorizationReference,
        'manifest_hash' => hash('sha256', 'exact-delivery-manifest'),
        'beneficiary_count' => 1,
        'principal_minor' => 2_000,
        'currency' => 'PHP',
        'status' => 'authorized',
    ]);
    $fulfillment = CampaignWorksheetFulfillment::query()->create([
        'campaign_worksheet_authorization_id' => $authorization->getKey(),
        'campaign_worksheet_row_id' => $row->getKey(),
        'mode' => 'pay_code_distribution',
        'status' => 'delivered',
        'pay_code' => $voucher->code,
    ]);
    $attempt = CampaignDeliveryAttempt::query()->create([
        'campaign_worksheet_authorization_id' => $authorization->getKey(),
        'campaign_worksheet_fulfillment_id' => $fulfillment->getKey(),
        'channel' => 'sms',
        'attempt_number' => 1,
        'idempotency_key_hash' => hash('sha256', 'exact-delivery-attempt'),
        'requested_by_type' => $owner->getMorphClass(),
        'requested_by_id' => (string) $owner->getKey(),
        'requested_at' => now(),
    ]);
    $attempt->events()->create([
        'sequence' => 1,
        'event_type' => 'completed',
        'provider_status' => 'ACCEPTED',
        'metadata' => ['feedback_delivery_id' => $exactDeliveryId],
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.read_model.feedback.deliveries.0.delivery_id', $exactDeliveryId)
        ->assertJsonPath('props.read_model.feedback.deliveries.0.intent_key', 'campaign.pay_code.delivery')
        ->assertJsonCount(1, 'props.read_model.feedback.deliveries');

    expect($response->getContent())
        ->toContain($exactDeliveryId)
        ->not->toContain($siblingDeliveryId)
        ->not->toContain('campaign.pay_code.sibling');
});
