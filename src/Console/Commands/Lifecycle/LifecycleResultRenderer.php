<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use Illuminate\Console\Command;
use Illuminate\Support\Number;

final class LifecycleResultRenderer
{
    public function render(Command $command, array $payload, int $exitCode = Command::SUCCESS): int
    {
        if ((bool) $command->option('json')) {
            $payload = $this->normalizePayloadForJson($payload);

            $command->line(json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) ?: '{}');

            return $exitCode;
        }

        $this->renderHuman($command, $payload);

        return $exitCode;
    }

    /**
     * Mirrors the old RunLifecycleScenarioCommand::renderResult() output.
     *
     * @param  array<string, mixed>  $payload
     */
    public function renderHuman(Command $command, array $payload): void
    {
        if (($payload['success'] ?? null) === false && isset($payload['message'])) {
            $command->error((string) $payload['message']);

            if (isset($payload['integrations']) && is_array($payload['integrations'])) {
                $this->renderIntegrations($command, $payload['integrations']);
            }

            return;
        }

        $command->info('Lifecycle scenario completed.');
        $command->line('Scenario: '.($payload['scenario'] ?? $payload['mode'] ?? 'n/a'));

        if (isset($payload['provider']) && $payload['provider'] !== null) {
            $command->line('Provider: '.$payload['provider']);
        }

        if (isset($payload['issuer']) && is_array($payload['issuer'])) {
            $issuerLabel = $payload['issuer']['email'] ?? ('#'.($payload['issuer']['id'] ?? 'n/a'));
            $issuerMobile = $payload['issuer']['mobile'] ?? 'n/a';

            $command->line("Issuer: {$issuerLabel} / {$issuerMobile}");
        }

        if (isset($payload['claim_mobile'])) {
            $command->line('Claim Mobile: '.$payload['claim_mobile']);
        }

        if (isset($payload['generated']['code'])) {
            $command->line('Voucher Code: '.$payload['generated']['code']);
        }

        if (isset($payload['attempt_summary']) && is_array($payload['attempt_summary'])) {
            $summary = $payload['attempt_summary'];

            $command->line(sprintf(
                'Attempts: %d/%d passed',
                (int) ($summary['passed'] ?? 0),
                (int) ($summary['total'] ?? 0),
            ));
        }

        if (isset($payload['phase_summary']) && is_array($payload['phase_summary'])) {
            $summary = $payload['phase_summary'];

            $command->line(sprintf(
                'Phases: %d/%d passed',
                (int) ($summary['passed'] ?? 0),
                (int) ($summary['total'] ?? 0),
            ));
        }

        $this->renderExecutionProjectionProfile($command, $payload);

        if (isset($payload['estimate']) && is_array($payload['estimate'])) {
            $this->renderEstimateSummary($command, $payload['estimate']);
        }

        if (isset($payload['generated']['wallet']['balance_before'], $payload['generated']['wallet']['balance_after'])) {
            $command->line(sprintf(
                'Wallet Balance: %s → %s',
                Number::currency(((float) $payload['generated']['wallet']['balance_before']) / 100, in: 'PHP'),
                Number::currency(((float) $payload['generated']['wallet']['balance_after']) / 100, in: 'PHP'),
            ));
        }

        if (isset($payload['money_semantics']) && is_array($payload['money_semantics'])) {
            $this->renderMoneySemantics($command, $payload['money_semantics']);
        }

        if (isset($payload['money_movement_decision']) && is_array($payload['money_movement_decision'])) {
            $this->renderMoneyMovementDecision($command, $payload['money_movement_decision']);
        }

        if (isset($payload['money_movement_target']) && is_array($payload['money_movement_target'])) {
            $this->renderMoneyMovementTarget($command, $payload['money_movement_target']);
        }

        if (isset($payload['money_movement_triggers']) && is_array($payload['money_movement_triggers'])) {
            $this->renderMoneyMovementTriggers($command, $payload['money_movement_triggers']);
        }

        if (! empty($payload['wallet_transactions']) && is_array($payload['wallet_transactions'])) {
            $command->newLine();
            $command->line('Recent Wallet Transactions:');
            $this->renderWalletTransactionsTable($command, $payload['wallet_transactions']);
        }

        if (isset($payload['reconciliation']) && is_array($payload['reconciliation']) && $payload['reconciliation'] !== []) {
            $command->newLine();
            $command->line('Reconciliation:');
            $this->lineIfPresent($command, 'Status', data_get($payload, 'reconciliation.status'));
            $this->lineIfPresent($command, 'Voucher Code', data_get($payload, 'reconciliation.voucher_code'));
            $this->lineIfPresent($command, 'Provider Reference', data_get($payload, 'reconciliation.provider_reference'));
            $this->lineIfPresent($command, 'Provider Status', data_get($payload, 'reconciliation.provider_status'));
        }

        if (isset($payload['disbursement_check']['current_status'])) {
            $command->line('Final Status: '.$payload['disbursement_check']['current_status']);
        }

        if (isset($payload['disbursement_check']['provider_transaction_id'])) {
            $command->line('Provider Transaction ID: '.($payload['disbursement_check']['provider_transaction_id'] ?: 'n/a'));
        }

        if (! empty($payload['disbursement_check']['timed_out'])) {
            $command->warn('Polling stopped before a terminal status was reached.');
        }

        if (isset($payload['integrations']) && is_array($payload['integrations'])) {
            $this->renderIntegrations($command, $payload['integrations']);
        }
    }

    /**
     * @param  array<string, mixed>  $moneySemantics
     */
    private function renderMoneySemantics(Command $command, array $moneySemantics): void
    {
        $after = data_get($moneySemantics, 'after_claim');

        if (! is_array($after)) {
            $after = data_get($moneySemantics, 'after_issuance');
        }

        if (! is_array($after)) {
            return;
        }

        $currency = (string) data_get($after, 'currency', 'PHP');

        $command->newLine();
        $command->line('Money Semantics:');
        $command->line('  Behavior: '.(string) data_get($moneySemantics, 'behavior', 'debit_at_issuance'));
        $command->line('  Wallet Balance: '.$this->formatMinorAmount(data_get($after, 'wallet_balance_minor'), $currency));
        $command->line('  Outstanding Pay Codes: '.$this->formatMinorAmount(data_get($after, 'outstanding_liability_minor'), $currency));
        $command->line('  Usable Balance Estimate: '.$this->formatMinorAmount(data_get($after, 'usable_balance_estimate_minor'), $currency));
    }

    private function formatMinorAmount(mixed $amount, string $currency): string
    {
        if (! is_numeric($amount)) {
            return 'n/a';
        }

        return Number::currency(((int) $amount) / 100, in: $currency);
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function renderMoneyMovementDecision(Command $command, array $decision): void
    {
        $command->newLine();
        $command->line('Money Movement Decision:');
        $command->line('  Status: '.(string) data_get($decision, 'status', 'decision_required'));
        $command->line('  Current Model: '.(string) data_get($decision, 'current_model', 'debit_at_issuance'));
        $command->line('  Recommended Next Model: '.(string) data_get($decision, 'recommended_next_model', 'reservation_release_pending_decision'));
        $command->line('  Mutates Wallets: '.($this->booleanLabel(data_get($decision, 'redactions.mutates_wallets'))));
        $command->line('  Reserves Funds: '.($this->booleanLabel(data_get($decision, 'redactions.reserves_funds'))));
        $command->line('  Releases Funds: '.($this->booleanLabel(data_get($decision, 'redactions.releases_funds'))));
    }

    private function booleanLabel(mixed $value): string
    {
        return $value === true ? 'yes' : 'no';
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function renderMoneyMovementTarget(Command $command, array $target): void
    {
        $command->newLine();
        $command->line('Money Movement Target:');
        $command->line('  Status: '.(string) data_get($target, 'status', 'pending_human_approval'));
        $command->line('  Recommended Model: '.(string) data_get($target, 'recommended_model', 'reserve_at_issuance_debit_at_redemption'));
        $command->line('  Selected Model: '.(string) (data_get($target, 'selected_model') ?: 'none'));
        $command->line('  Requires Approval: '.$this->booleanLabel(data_get($target, 'requires_human_approval')));
    }

    /**
     * @param  array<string, mixed>  $matrix
     */
    private function renderMoneyMovementTriggers(Command $command, array $matrix): void
    {
        $triggers = data_get($matrix, 'triggers');

        if (! is_array($triggers)) {
            return;
        }

        $command->newLine();
        $command->line('Money Movement Triggers:');
        $command->line('  Status: '.(string) data_get($matrix, 'status', 'planning_only'));
        $command->line('  Planned Triggers: '.count($triggers));
        $command->line('  Enabled: no');
    }

    private function lineIfPresent(Command $command, string $label, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $command->line(sprintf(
            '%s: %s',
            $label,
            is_scalar($value) ? (string) $value : (json_encode($value) ?: 'n/a')
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderExecutionProjectionProfile(Command $command, array $payload): void
    {
        $profile = data_get($payload, 'execution.projection_profile');

        if (! is_array($profile)) {
            return;
        }

        $command->line('Execution Projection: '.(string) ($profile['status'] ?? 'n/a'));
        $command->line('Cockpit Projection Source: '.(string) data_get($profile, 'cockpit_projection.source', 'n/a'));

        $targets = data_get($profile, 'projected_targets');

        if (is_array($targets) && $targets !== []) {
            $command->line('Projected Targets: '.implode(', ', array_map(
                static fn (mixed $target): string => is_scalar($target) ? (string) $target : 'unknown',
                $targets,
            )));
        }
    }

    /**
     * @param  array<string, mixed>  $estimate
     */
    private function renderEstimateSummary(Command $command, array $estimate): void
    {
        $currency = (string) ($estimate['currency'] ?? 'PHP');

        if (isset($estimate['total'])) {
            $command->line('Estimated Tariff: '.Number::currency((float) $estimate['total'], in: $currency));
        }

        $charges = $estimate['charges'] ?? null;

        if (! is_array($charges) || $charges === []) {
            return;
        }

        $command->line('Charge Lines:');

        foreach ($charges as $charge) {
            $label = (string) ($charge['label'] ?? $charge['index'] ?? 'Unknown');
            $quantity = (int) ($charge['quantity'] ?? 1);
            $unitPrice = (float) ($charge['unit_price'] ?? 0);
            $price = (float) ($charge['price'] ?? 0);
            $chargeCurrency = (string) ($charge['currency'] ?? $currency);

            $command->line(sprintf(
                '  - %s | %s × %d = %s',
                $label,
                Number::currency($unitPrice, in: $chargeCurrency),
                $quantity,
                Number::currency($price, in: $chargeCurrency),
            ));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     */
    private function renderWalletTransactionsTable(Command $command, array $transactions): void
    {
        $rows = array_map(function (array $tx): array {
            return [
                $tx['id'] ?? 'n/a',
                $tx['type'] ?? 'n/a',
                $tx['formatted_amount'] ?? Number::currency((float) ($tx['amount'] ?? 0), in: (string) ($tx['currency'] ?? 'PHP')),
                $tx['reason'] ?? 'n/a',
                $tx['voucher_code'] ?? data_get($tx, 'meta.voucher_code', 'n/a'),
                $tx['idempotency_key'] ?? 'n/a',
                $tx['created_at'] ?? 'n/a',
            ];
        }, $transactions);

        $command->table(
            ['ID', 'Type', 'Amount', 'Reason', 'Voucher', 'Idempotency Key', 'Created At'],
            $rows
        );
    }

    /**
     * @param  array<string, mixed>  $integrations
     */
    private function renderIntegrations(Command $command, array $integrations): void
    {
        $command->newLine();
        $command->line('Integrations:');

        foreach ([
            'journal' => 'Journal',
            'actions' => 'Actions',
            'feedback' => 'Feedback',
            'campaigns' => 'Campaigns',
        ] as $key => $label) {
            $status = (string) data_get($integrations, "{$key}.status", 'unavailable');
            $reason = data_get($integrations, "{$key}.redactions.reason");

            $command->line(sprintf(
                '  %s: %s%s',
                $label,
                $status,
                is_scalar($reason) && trim((string) $reason) !== '' ? ' ('.trim((string) $reason).')' : '',
            ));
        }

        if (data_get($integrations, 'summary.read_only') === true) {
            $command->line('  Mode: read-only; no journal writes, action execution, feedback delivery, campaign mutation, or money movement.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayloadForJson(array $payload): array
    {
        $attempts = data_get($payload, 'attempts');

        if (is_array($attempts) && ! array_is_list($attempts)) {
            $payload['attempts'] = collect($attempts)
                ->map(function (array $attempt, string $name) {
                    return array_merge(['name' => $name], $attempt);
                })
                ->values()
                ->all();
        }

        $claims = data_get($payload, 'claims');

        if (is_array($claims) && ! array_is_list($claims)) {
            $payload['claims'] = collect($claims)
                ->map(function (array $claim, string $name) {
                    return array_merge(['name' => $name], $claim);
                })
                ->values()
                ->all();
        }

        return $payload;
    }
}
