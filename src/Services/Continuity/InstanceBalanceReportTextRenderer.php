<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Continuity;

final class InstanceBalanceReportTextRenderer
{
    /** @param array<string, mixed> $report */
    public function render(array $report): string
    {
        $lines = [
            'X-Change Instance Balance Report',
            '================================',
            'Status: '.(string) data_get($report, 'status'),
            'As of: '.(string) data_get($report, 'as_of'),
            'Instance: '.(string) (data_get($report, 'source_instance.id') ?? 'not configured'),
            'X-Change: '.(string) data_get($report, 'source_instance.x_change_version'),
            '',
            'Treasury connections',
            '--------------------',
        ];

        foreach ((array) ($report['treasury_connections'] ?? []) as $connection) {
            $lines[] = sprintf(
                '%s | %s | %s | inventory minor %s | positions minor %s | control %s',
                (string) ($connection['reference'] ?? ''),
                (string) ($connection['provider'] ?? ''),
                (string) ($connection['currency'] ?? ''),
                $this->minor(data_get($connection, 'inventory.balance_minor')),
                $this->minor(data_get($connection, 'positions.balance_minor')),
                data_get($connection, 'control.inventory_equals_positions') === true ? 'matched' : 'attention',
            );
        }

        $lines[] = '';
        $lines[] = 'Accounts';
        $lines[] = '--------';

        foreach ((array) ($report['accounts'] ?? []) as $account) {
            $lines[] = sprintf(
                '%s | %s | %d Pay Code records',
                (string) ($account['account_reference'] ?? ''),
                (string) (data_get($account, 'identifier.email') ?? data_get($account, 'identifier.mobile') ?? 'masked'),
                (int) data_get($account, 'pay_code_record_facts.count', 0),
            );

            foreach ((array) ($account['balances'] ?? []) as $balance) {
                $lines[] = sprintf(
                    '  %s / %s / %s | Client Funds minor %s | Outstanding Pay Codes minor %s | Issuance Capacity minor %s',
                    (string) ($balance['provider'] ?? ''),
                    (string) ($balance['connection_reference'] ?? ''),
                    (string) ($balance['currency'] ?? ''),
                    $this->minor($balance['client_funds_minor'] ?? null),
                    $this->minor($balance['outstanding_pay_codes_minor'] ?? null),
                    $this->minor(data_get($balance, 'issuance_capacity.amount_minor')),
                );
            }
        }

        $lines[] = '';
        $lines[] = 'Blockers: '.($report['blockers'] === [] ? 'none' : implode(', ', $report['blockers']));
        $lines[] = 'Warnings: '.($report['warnings'] === [] ? 'none' : implode(', ', $report['warnings']));
        $lines[] = '';
        $lines[] = (string) ($report['disclaimer'] ?? '');

        return implode("\n", $lines)."\n";
    }

    private function minor(mixed $value): string
    {
        return is_int($value) ? (string) $value : 'unavailable';
    }
}
