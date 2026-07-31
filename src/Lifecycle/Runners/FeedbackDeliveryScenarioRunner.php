<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Feedback\SendTestFeedback;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use Throwable;

final readonly class FeedbackDeliveryScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private SendTestFeedback $feedback,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $live = (bool) data_get($context->scenario, '_runtime.live_feedback', false);
        $runReference = (string) (
            data_get($context->scenario, '_runtime.run_reference')
            ?: $context->idempotencyKey
        );
        $routes = array_filter([
            'email' => $this->stringValue(data_get($context->scenario, '_runtime.feedback_email'))
                ?: $this->stringValue(data_get($context->scenario, 'feedback.email')),
            'sms' => $this->stringValue(data_get($context->scenario, '_runtime.feedback_mobile'))
                ?: $this->stringValue(data_get($context->scenario, 'feedback.mobile')),
        ]);

        if ($routes === []) {
            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'scenario' => $context->scenarioKey,
                    'label' => $context->label(),
                    'mode' => 'feedback_delivery',
                    'live' => $live,
                    'message' => 'Feedback lifecycle requires an email address, mobile number, or both.',
                ],
            );
        }

        $deliveries = [];
        $successful = true;
        $journalEvents = 0;

        foreach ($routes as $channel => $route) {
            try {
                $result = $this->feedback->handle(
                    channel: $channel,
                    route: $route,
                    message: (string) data_get(
                        $context->scenario,
                        'feedback.message',
                        'X-Change lifecycle feedback delivery is configured and working.',
                    ),
                    runReference: $runReference.':'.$channel,
                    send: $live,
                    title: (string) data_get(
                        $context->scenario,
                        'feedback.subject',
                        'X-Change lifecycle feedback test',
                    ),
                );
                $deliveries[] = $result->toArray();
                $journalEvents += count($result->journalEventTypes);

                if ($live && ! in_array($result->status, [
                    FeedbackDeliveryData::StatusQueued,
                    FeedbackDeliveryData::StatusSent,
                    FeedbackDeliveryData::StatusDelivered,
                ], true)) {
                    $successful = false;
                }
            } catch (Throwable $exception) {
                report($exception);
                $successful = false;
                $deliveries[] = [
                    'status' => 'failed',
                    'channel' => $channel,
                    'error' => $exception::class,
                    'provider_side_effect' => false,
                ];
            }
        }

        return new ScenarioRunResult(
            exitCode: $successful ? Command::SUCCESS : Command::FAILURE,
            payload: [
                'success' => $successful,
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => 'feedback_delivery',
                'live' => $live,
                'run_reference' => $runReference,
                'deliveries' => $deliveries,
                'journal' => [
                    'events' => $journalEvents,
                    'append_only' => true,
                    'source' => 'x-journal',
                ],
                'safety' => [
                    'issues_vouchers' => false,
                    'moves_money' => false,
                    'mutates_treasury' => false,
                    'uses_laravel_notifications' => false,
                ],
            ],
        );
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
