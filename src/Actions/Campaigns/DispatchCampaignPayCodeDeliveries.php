<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRuntimeContract;
use LBHurtado\XFeedback\Contracts\FeedbackDispatchPreparerContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackChannelSelectionPolicyData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;
use Throwable;

final class DispatchCampaignPayCodeDeliveries
{
    public function __construct(
        private readonly RecordCampaignDeliveryAttempt $deliveryAttempts,
        private readonly FeedbackDispatchPreparerContract $preparer,
        private readonly FeedbackDeliveryAttemptRuntimeContract $runtime,
        private readonly FeedbackDeliveryAttemptRecorderContract $recorder,
    ) {}

    /**
     * @return array{sent: int, failed: int, blocked: int, skipped: int}
     */
    public function handle(
        CampaignWorksheetAuthorization $authorization,
        Model $actor,
        string $channel,
        int $limit = 100,
    ): array {
        $this->assertChannel($channel);
        $result = ['sent' => 0, 'failed' => 0, 'blocked' => 0, 'skipped' => 0];

        $fulfillments = $authorization->fulfillments()
            ->with('row')
            ->where('status', 'issued')
            ->whereNotNull('pay_code')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($fulfillments as $fulfillment) {
            $alreadyAttempted = CampaignDeliveryAttempt::query()
                ->where('campaign_worksheet_fulfillment_id', $fulfillment->getKey())
                ->where('channel', $channel)
                ->exists();

            if ($alreadyAttempted) {
                $result['skipped']++;

                continue;
            }

            $outcome = $this->dispatch($authorization, $fulfillment, $actor, $channel);
            $result[$outcome]++;
        }

        return $result;
    }

    public function retry(CampaignDeliveryAttempt $previous, Model $actor): string
    {
        $previous->loadMissing(['authorization', 'fulfillment.row', 'events']);
        $lastEvent = $previous->events->last();

        if (! in_array($lastEvent?->event_type, ['failed', 'blocked'], true)
            || ! $previous->authorization instanceof CampaignWorksheetAuthorization
            || ! $previous->fulfillment instanceof CampaignWorksheetFulfillment) {
            throw new RuntimeException('Only failed or blocked beneficiary deliveries can be retried.');
        }

        return $this->dispatch(
            $previous->authorization,
            $previous->fulfillment,
            $actor,
            $previous->channel,
            (string) $previous->reference,
        );
    }

    private function dispatch(
        CampaignWorksheetAuthorization $authorization,
        CampaignWorksheetFulfillment $fulfillment,
        Model $actor,
        string $channel,
        ?string $retryOfReference = null,
    ): string {
        $beneficiary = (array) ($fulfillment->row?->beneficiary_ciphertext ?? []);
        $route = $channel === 'sms'
            ? $this->stringValue($beneficiary['mobile'] ?? null)
            : $this->stringValue($beneficiary['email'] ?? null);
        $attempt = $this->deliveryAttempts->start(
            authorization: $authorization,
            channel: $channel,
            actor: $actor,
            idempotencyKey: implode(':', [
                'campaign-delivery',
                (string) $authorization->reference,
                (string) $fulfillment->reference,
                $channel,
                (string) ($retryOfReference ?? 'initial'),
            ]),
            fulfillment: $fulfillment,
            recipientRoute: $route,
            retryOfReference: $retryOfReference,
            metadata: [
                'pay_code' => $fulfillment->pay_code,
                'recipient_type' => 'campaign_beneficiary',
            ],
        );

        if ($route === null) {
            $this->deliveryAttempts->append($attempt, 'blocked', safeErrorCode: 'recipient_route_missing');

            return 'blocked';
        }

        try {
            $preparation = $this->preparer->prepare(
                $this->intent($authorization, $fulfillment, $beneficiary, $channel, $route, $attempt),
                new FeedbackChannelSelectionPolicyData(
                    allowed_channels: [$channel],
                    preferred_channels: [$channel],
                    required_channels: [$channel],
                    profile: 'campaign_pay_code_delivery',
                    meta: ['explicit_operator_action' => true],
                ),
            );

            if ($preparation->plan->items === []) {
                $this->deliveryAttempts->append($attempt, 'blocked', safeErrorCode: 'delivery_plan_unavailable');

                return 'blocked';
            }

            $runtimeAttempt = $this->runtime->execute($preparation);
            $this->recorder->record($runtimeAttempt);
            $delivery = $runtimeAttempt->deliveries[0] ?? null;

            if (! $delivery instanceof FeedbackDeliveryData) {
                $this->deliveryAttempts->append($attempt, 'failed', safeErrorCode: 'provider_outcome_missing');

                return 'failed';
            }

            if (str_starts_with($delivery->status, 'failed')) {
                $this->deliveryAttempts->append(
                    $attempt,
                    'failed',
                    providerStatus: $delivery->status,
                    providerDeliveryReference: $delivery->provider_message_id,
                    safeErrorCode: 'provider_delivery_failed',
                );

                return 'failed';
            }

            $this->deliveryAttempts->append(
                $attempt,
                'completed',
                providerStatus: $delivery->status,
                providerDeliveryReference: $delivery->provider_message_id,
            );

            return 'sent';
        } catch (Throwable) {
            $this->deliveryAttempts->append($attempt, 'failed', safeErrorCode: 'delivery_runtime_failed');

            return 'failed';
        }
    }

    /**
     * @param  array<string, mixed>  $beneficiary
     */
    private function intent(
        CampaignWorksheetAuthorization $authorization,
        CampaignWorksheetFulfillment $fulfillment,
        array $beneficiary,
        string $channel,
        string $route,
        CampaignDeliveryAttempt $attempt,
    ): FeedbackIntentData {
        $claimUrl = route('x-change.claim.start', $fulfillment->pay_code);

        return FeedbackIntentData::forEvent(
            key: 'campaign.pay_code.delivery',
            eventType: 'campaign.pay_code.delivery.requested',
            message: new FeedbackMessageData(
                title: 'Your Pay Code is ready',
                body: sprintf('Pay Code %s is ready. Claim it at %s', $fulfillment->pay_code, $claimUrl),
                summary: sprintf('Pay Code %s', $fulfillment->pay_code),
                actions: [['label' => 'Claim Pay Code', 'href' => $claimUrl, 'type' => 'link']],
                meta: ['provider_delivery' => true],
            ),
            recipients: [
                new FeedbackRecipientData(
                    type: 'campaign_beneficiary',
                    id: (string) $fulfillment->reference,
                    name: $this->stringValue($beneficiary['name'] ?? null),
                    email: $channel === 'email' ? $route : null,
                    phone: $channel === 'sms' ? $route : null,
                ),
            ],
            channels: [new FeedbackChannelData(key: $channel)],
            source: 'x-change.campaigns',
            correlationId: (string) $authorization->reference,
            causationId: (string) $attempt->reference,
            subjectType: 'campaign_worksheet_fulfillment',
            subjectId: (string) $fulfillment->reference,
            meta: ['explicit_operator_action' => true],
        );
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, ['sms', 'email'], true)) {
            throw new RuntimeException('Campaign delivery channel is not supported.');
        }
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
