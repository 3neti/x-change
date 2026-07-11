<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Cockpit;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

class SeedCockpitDiagnosticActivityCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:cockpit:seed-diagnostic-activity
        {--local-only : Required safety flag for local diagnostic fixture seeding}
        {--activity-id=fixture-cockpit-journal-diagnostic-activity : Synthetic activity id}
        {--code=PC-LOCAL-DIAGNOSTIC : Synthetic Pay Code reference}
        {--operator-id=local-fixture-operator : Synthetic/operator id that should see the fixture in Cockpit}
        {--with-action : Include synthetic x-action composed handoff facts}
        {--with-feedback : Include synthetic x-feedback planned handoff facts}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Seed one safe local-only Cockpit durable activity diagnostic fixture.';

    public function handle(DatabaseCockpitOperatorIssuanceActivityRepository $repository): int
    {
        if (! (bool) $this->option('local-only')) {
            $this->error('Refusing to seed Cockpit diagnostic activity without the explicit --local-only flag.');

            return self::FAILURE;
        }

        if ($this->isProductionEnvironment()) {
            $this->error('Refusing to seed Cockpit diagnostic activity in production.');

            return self::FAILURE;
        }

        $record = $repository->record($this->fixtureRecord());

        $payload = [
            'seeded' => true,
            'local_only' => true,
            'activity_id' => $record->activity_id,
            'operator_id' => $record->actor_id,
            'code' => $record->subject_reference,
            'journal_handoff_status' => $record->journal_handoff_status,
            'action_handoff_status' => $record->action_handoff_status,
            'feedback_handoff_status' => $record->feedback_handoff_status,
            'dashboard_ready' => $this->dashboardReady(),
            'dashboard_repository' => config('x-change.cockpit.operator_issuance_activity.repository'),
            'next_step' => $this->dashboardReady()
                ? 'Open /x/cockpit and verify the populated Operator Issuance Activity diagnostic card.'
                : 'Set XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY to the database repository before browser verification.',
            'safety' => [
                'writes_journal' => false,
                'executes_action' => false,
                'sends_feedback' => false,
                'calls_provider' => false,
                'mutates_voucher' => false,
                'touches_wallet' => false,
                'moves_money' => false,
                'raw_payloads_exposed' => false,
            ],
        ];

        $this->renderPayload($payload, 'Seeded local Cockpit diagnostic activity fixture.');

        return self::SUCCESS;
    }

    private function fixtureRecord(): CockpitOperatorIssuanceActivityRecordData
    {
        $activityId = $this->nonEmptyOption('activity-id', 'fixture-cockpit-journal-diagnostic-activity');
        $code = $this->nonEmptyOption('code', 'PC-LOCAL-DIAGNOSTIC');
        $operatorId = $this->nonEmptyOption('operator-id', 'local-fixture-operator');

        return new CockpitOperatorIssuanceActivityRecordData(
            activity_id: $activityId,
            actor_id: $operatorId,
            actor_label: 'Treasury Operations',
            source: 'cockpit.local-diagnostic-fixture',
            subject_type: 'pay_code',
            subject_reference: $code,
            status: 'issued',
            severity: 'info',
            occurred_at: now()->toAtomString(),
            idempotency_key_hash: hash('sha256', 'fixture-redacted-idempotency-key'),
            correlation_id: 'corr-local-cockpit-diagnostic',
            causation_id: 'cause-local-cockpit-diagnostic',
            summary: "Synthetic local diagnostic activity for {$code}",
            safe_context: [
                'amount' => '25.00',
                'currency' => 'PHP',
                'route' => 'x-change.cockpit.local-diagnostic-fixture',
                'detail_href' => "/x/cockpit/pay-codes/{$code}",
                'fixture' => true,
                'local_only' => true,
            ],
            redaction_flags: [
                'raw_payloads_exposed' => false,
                'provider_payloads_exposed' => false,
                'wallet_data_exposed' => false,
                'recipient_secrets_exposed' => false,
            ],
            journal_handoff_status: 'recorded',
            action_handoff_status: $this->withActionFixture() ? 'composed' : 'not_wired',
            feedback_handoff_status: $this->withFeedbackFixture() ? 'planned' : 'not_wired',
            metadata: array_filter([
                'fixture' => true,
                'local_only' => true,
                'journal_handoff' => [
                    'status' => 'recorded',
                    'journal_entry_id' => 'journal-entry-local-fixture',
                    'writes_journal' => true,
                    'source' => 'local_fixture',
                    'reason' => 'Synthetic local fixture for Cockpit diagnostic visual verification.',
                    'metadata' => [
                        'reference_number' => 'ERN-LOCAL-COCKPIT-0001',
                        'event_type' => 'cockpit.operator_issuance_activity.fixture',
                        'idempotency_key' => 'fixture-redacted-idempotency-key',
                    ],
                ],
                'action_handoff' => $this->withActionFixture() ? [
                    'status' => 'composed',
                    'action_hint_id' => 'cockpit.pay-code.open',
                    'action_run_id' => 'action-run-local-fixture',
                    'action_required' => false,
                    'executes_action' => false,
                    'source' => 'local_fixture',
                    'reason' => 'Synthetic local x-action fixture for Cockpit diagnostic visual verification.',
                    'metadata' => [
                        'event_or_state' => 'cockpit.operator_issuance_activity.fixture',
                        'actions' => [
                            [
                                'key' => 'cockpit.pay-code.open',
                                'label' => 'Open Pay Code',
                                'run_id' => 'action-run-local-fixture',
                                'target' => [
                                    'url' => "/x/cockpit/pay-codes/{$code}",
                                    'redirectable' => true,
                                ],
                            ],
                        ],
                        'composition' => [
                            'presentation_only' => true,
                            'executes_action' => false,
                        ],
                    ],
                ] : null,
                'feedback_handoff' => $this->withFeedbackFixture() ? [
                    'status' => 'planned',
                    'feedback_intent_id' => 'cockpit.operator_issuance_activity.fixture',
                    'delivery_plan_id' => 'plan-local-fixture',
                    'delivery_receipt_id' => null,
                    'feedback_required' => false,
                    'sends_feedback' => false,
                    'source' => 'local_fixture',
                    'reason' => 'Synthetic local x-feedback fixture for Cockpit diagnostic visual verification.',
                    'metadata' => [
                        'intent_key' => 'cockpit.operator_issuance_activity.fixture',
                        'event_type' => 'cockpit.operator_issuance_activity.fixture',
                        'delivery_boundary' => 'prepare_only',
                        'planned_deliveries' => 1,
                        'channels' => ['in_app'],
                        'plan_items' => [
                            [
                                'intent_key' => 'cockpit.operator_issuance_activity.fixture',
                                'recipient_type' => 'operator',
                                'recipient_id' => $operatorId,
                                'channel' => 'in_app',
                                'status' => 'planned',
                                'priority' => 100,
                                'correlation_id' => 'corr-local-cockpit-diagnostic',
                                'causation_id' => 'cause-local-cockpit-diagnostic',
                            ],
                        ],
                        'composition' => [
                            'presentation_only' => true,
                            'sends_feedback' => false,
                            'owns_lifecycle_truth' => false,
                        ],
                    ],
                ] : null,
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    private function withActionFixture(): bool
    {
        return (bool) $this->option('with-action');
    }

    private function withFeedbackFixture(): bool
    {
        return (bool) $this->option('with-feedback');
    }

    private function nonEmptyOption(string $key, string $fallback): string
    {
        $value = $this->option($key);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : $fallback;
    }

    private function isProductionEnvironment(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }

    private function dashboardReady(): bool
    {
        return Arr::get(
            config('x-change.cockpit.operator_issuance_activity', []),
            'repository',
        ) === DatabaseCockpitOperatorIssuanceActivityRepository::class;
    }
}
