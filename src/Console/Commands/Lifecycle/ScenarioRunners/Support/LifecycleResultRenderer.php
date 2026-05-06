<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

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

            return;
        }

        $command->info('Lifecycle scenario completed.');
        $command->line('Scenario: '.($payload['scenario'] ?? $payload['mode'] ?? 'n/a'));

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
