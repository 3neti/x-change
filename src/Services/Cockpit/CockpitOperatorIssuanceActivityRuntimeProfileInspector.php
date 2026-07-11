<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileData;

class CockpitOperatorIssuanceActivityRuntimeProfileInspector
{
    /**
     * @var array<string, array{available: string, fallback: class-string, purpose: string}>
     */
    private const Components = [
        'repository' => [
            'available' => 'available_repositories',
            'fallback' => NullCockpitOperatorIssuanceActivityRepository::class,
            'purpose' => 'Durable activity read storage',
        ],
        'recorder' => [
            'available' => 'available_recorders',
            'fallback' => NullCockpitOperatorIssuanceActivityRecorder::class,
            'purpose' => 'Post-issuance activity recording',
        ],
        'journal_handoff' => [
            'available' => 'available_journal_handoffs',
            'fallback' => NullCockpitOperatorIssuanceActivityJournalHandoff::class,
            'purpose' => 'x-journal evidence handoff',
        ],
        'journal_handoff_status_projector' => [
            'available' => 'available_journal_handoff_status_projectors',
            'fallback' => NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class,
            'purpose' => 'Journal handoff status persistence',
        ],
        'action_handoff' => [
            'available' => 'available_action_handoffs',
            'fallback' => NullCockpitOperatorIssuanceActivityActionHandoff::class,
            'purpose' => 'x-action presentation-only hint handoff',
        ],
        'action_handoff_status_projector' => [
            'available' => 'available_action_handoff_status_projectors',
            'fallback' => NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class,
            'purpose' => 'Action handoff status persistence',
        ],
        'feedback_handoff' => [
            'available' => 'available_feedback_handoffs',
            'fallback' => NullCockpitOperatorIssuanceActivityFeedbackHandoff::class,
            'purpose' => 'x-feedback notification-planning handoff',
        ],
        'feedback_handoff_status_projector' => [
            'available' => 'available_feedback_handoff_status_projectors',
            'fallback' => NullCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class,
            'purpose' => 'Feedback handoff status persistence',
        ],
    ];

    public function inspect(): CockpitOperatorIssuanceActivityRuntimeProfileData
    {
        $components = array_map(
            fn (string $key, array $definition): array => $this->component($key, $definition),
            array_keys(self::Components),
            array_values(self::Components),
        );

        $repositoryEnabled = $this->enabled('repository');
        $recorderEnabled = $this->enabled('recorder');
        $journalEnabled = $this->enabled('journal_handoff');
        $actionEnabled = $this->enabled('action_handoff');
        $feedbackEnabled = $this->enabled('feedback_handoff');

        return new CockpitOperatorIssuanceActivityRuntimeProfileData(
            status: $this->status($repositoryEnabled, $recorderEnabled, $journalEnabled, $actionEnabled, $feedbackEnabled),
            repository_enabled: $repositoryEnabled,
            recorder_enabled: $recorderEnabled,
            journal_handoff_enabled: $journalEnabled,
            action_handoff_enabled: $actionEnabled,
            feedback_handoff_enabled: $feedbackEnabled,
            components: $components,
            safety: [
                'defaults_safe' => ! $repositoryEnabled
                    && ! $recorderEnabled
                    && ! $journalEnabled
                    && ! $actionEnabled
                    && ! $feedbackEnabled,
                'requires_explicit_opt_in' => true,
                'moves_money' => false,
                'calls_provider' => false,
                'executes_action' => false,
                'sends_feedback' => false,
                'writes_journal' => $journalEnabled,
                'owns_lifecycle_truth' => false,
            ],
        );
    }

    /**
     * @param  array{available: string, fallback: class-string, purpose: string}  $definition
     * @return array<string, mixed>
     */
    private function component(string $key, array $definition): array
    {
        $configured = $this->configured($key);
        $resolved = $this->resolved($key, $definition['available'], $definition['fallback']);

        return [
            'key' => $key,
            'configured' => $configured,
            'enabled' => $configured !== null,
            'resolved_class' => $resolved,
            'fallback_class' => $definition['fallback'],
            'uses_fallback' => $configured === null,
            'purpose' => $definition['purpose'],
        ];
    }

    private function status(
        bool $repositoryEnabled,
        bool $recorderEnabled,
        bool $journalEnabled,
        bool $actionEnabled,
        bool $feedbackEnabled,
    ): string {
        if (! $repositoryEnabled && ! $recorderEnabled && ! $journalEnabled && ! $actionEnabled && ! $feedbackEnabled) {
            return 'not_wired';
        }

        if ($repositoryEnabled && $recorderEnabled && $journalEnabled && $actionEnabled && $feedbackEnabled) {
            return 'combined_runtime_ready';
        }

        return 'partially_wired';
    }

    private function enabled(string $key): bool
    {
        return $this->configured($key) !== null;
    }

    private function configured(string $key): ?string
    {
        $configured = config("x-change.cockpit.operator_issuance_activity.{$key}");

        return is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : null;
    }

    private function resolved(string $configuredKey, string $availableKey, string $fallback): string
    {
        $configured = $this->configured($configuredKey);

        if ($configured === null) {
            return $fallback;
        }

        $available = config("x-change.cockpit.operator_issuance_activity.{$availableKey}", []);

        if (is_array($available) && is_string($available[$configured] ?? null)) {
            return $available[$configured];
        }

        return $configured;
    }
}
