<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Lifecycle\LifecycleResultRenderer;

it('renders json payload when json option is enabled', function () {
    $command = new class extends Command
    {
        public array $lines = [];

        public function option($key = null): mixed
        {
            return $key === 'json';
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->lines[] = $string;
        }
    };

    $exitCode = app(LifecycleResultRenderer::class)->render(
        command: $command,
        payload: [
            'scenario' => 'basic_cash',
            'label' => 'Basic Cash',
        ],
        exitCode: 0,
    );

    expect($exitCode)->toBe(0)
        ->and($command->lines)->toHaveCount(1)
        ->and(json_decode($command->lines[0], true))->toMatchArray([
            'scenario' => 'basic_cash',
            'label' => 'Basic Cash',
        ]);
});

it('renders human lifecycle summary when json option is disabled', function () {
    $command = new class extends Command
    {
        public array $capturedOutput = [];

        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void
        {
            $this->capturedOutput[] = $string;
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->capturedOutput[] = $string;
        }

        public function newLine($count = 1): void
        {
            $this->capturedOutput[] = '';
        }
    };

    $exitCode = app(LifecycleResultRenderer::class)->render(
        command: $command,
        payload: [
            'scenario' => 'basic_cash',
            'label' => 'Basic Cash',
            'mode' => 'default',
            'generated' => [
                'code' => 'ABCD',
            ],
            'attempt_summary' => [
                'passed' => 1,
                'failed' => 0,
                'total' => 1,
            ],
            'money_semantics' => [
                'behavior' => 'debit_at_issuance',
                'after_issuance' => [
                    'currency' => 'PHP',
                    'wallet_balance_minor' => 97500,
                    'outstanding_liability_minor' => 2500,
                    'usable_balance_estimate_minor' => 95000,
                ],
            ],
            'money_movement_decision' => [
                'status' => 'decision_required',
                'current_model' => 'debit_at_issuance',
                'recommended_next_model' => 'reservation_release_pending_decision',
                'redactions' => [
                    'mutates_wallets' => false,
                    'reserves_funds' => false,
                    'releases_funds' => false,
                ],
            ],
            'money_movement_target' => [
                'status' => 'pending_human_approval',
                'recommended_model' => 'reserve_at_issuance_debit_at_redemption',
                'selected_model' => null,
                'requires_human_approval' => true,
            ],
            'money_movement_triggers' => [
                'status' => 'planning_only',
                'triggers' => [
                    ['event' => 'pay_code_issued', 'enabled' => false],
                    ['event' => 'pay_code_redeemed', 'enabled' => false],
                ],
            ],
        ],
        exitCode: 0,
    );

    expect($exitCode)->toBe(0)
        ->and($command->capturedOutput)->toContain('Lifecycle scenario completed.')
        ->and($command->capturedOutput)->toContain('Scenario: basic_cash')
        ->and($command->capturedOutput)->toContain('Voucher Code: ABCD')
        ->and($command->capturedOutput)->toContain('Attempts: 1/1 passed')
        ->and($command->capturedOutput)->toContain('Money Semantics:')
        ->and($command->capturedOutput)->toContain('  Behavior: debit_at_issuance')
        ->and($command->capturedOutput)->toContain('  Outstanding Pay Codes: ₱25.00')
        ->and($command->capturedOutput)->toContain('  Usable Balance Estimate: ₱950.00')
        ->and($command->capturedOutput)->toContain('Money Movement Decision:')
        ->and($command->capturedOutput)->toContain('  Status: decision_required')
        ->and($command->capturedOutput)->toContain('  Current Model: debit_at_issuance')
        ->and($command->capturedOutput)->toContain('  Releases Funds: no')
        ->and($command->capturedOutput)->toContain('Money Movement Target:')
        ->and($command->capturedOutput)->toContain('  Status: pending_human_approval')
        ->and($command->capturedOutput)->toContain('  Recommended Model: reserve_at_issuance_debit_at_redemption')
        ->and($command->capturedOutput)->toContain('  Selected Model: none')
        ->and($command->capturedOutput)->toContain('Money Movement Triggers:')
        ->and($command->capturedOutput)->toContain('  Status: planning_only')
        ->and($command->capturedOutput)->toContain('  Planned Triggers: 2')
        ->and($command->capturedOutput)->toContain('  Enabled: no');
});

it('renders phase summary reconciliation and wallet transactions when present', function () {
    $command = new class extends Command
    {
        public array $capturedOutput = [];

        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void
        {
            $this->capturedOutput[] = $string;
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->capturedOutput[] = $string;
        }

        public function newLine($count = 1): void
        {
            $this->capturedOutput[] = '';
        }

        public function table($headers, $rows, $tableStyle = 'default', array $columnStyles = []): void
        {
            foreach ($rows as $row) {
                $this->capturedOutput[] = implode(' | ', array_map(
                    static fn (mixed $value): string => (string) $value,
                    $row,
                ));
            }
        }
    };

    app(LifecycleResultRenderer::class)->render(
        command: $command,
        payload: [
            'phase_summary' => [
                'passed' => 5,
                'failed' => 0,
                'total' => 5,
            ],
            'reconciliation' => [
                'status' => 'pending_review',
                'voucher_code' => 'ABCD',
                'provider_reference' => 'provider-123',
                'provider_status' => 'failed',
            ],
            'wallet_transactions' => [
                [
                    'id' => 1,
                    'type' => 'deposit',
                    'amount' => '100.00',
                    'meta' => [
                        'voucher_code' => 'ABCD',
                    ],
                ],
            ],
        ],
        exitCode: 0,
    );

    expect($command->capturedOutput)->toContain('Phases: 5/5 passed')
        ->and($command->capturedOutput)->toContain('Reconciliation:')
        ->and($command->capturedOutput)->toContain('Recent Wallet Transactions:')
        ->and(collect($command->capturedOutput)->contains(fn (string $line): bool => str_contains($line, 'provider-123')))->toBeTrue()
        ->and(collect($command->capturedOutput)->contains(fn (string $line): bool => str_contains($line, 'deposit')))->toBeTrue();
});

it('renders integration reports for failed human lifecycle payloads', function () {
    $command = new class extends Command
    {
        public array $capturedOutput = [];

        public function option($key = null): mixed
        {
            return false;
        }

        public function error($string, $verbosity = null): void
        {
            $this->capturedOutput[] = $string;
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->capturedOutput[] = $string;
        }

        public function newLine($count = 1): void
        {
            $this->capturedOutput[] = '';
        }
    };

    app(LifecycleResultRenderer::class)->render(
        command: $command,
        payload: [
            'success' => false,
            'message' => 'Unknown lifecycle scenario',
            'integrations' => [
                'journal' => ['status' => 'unavailable', 'redactions' => ['reason' => 'missing-code']],
                'actions' => ['status' => 'available'],
                'feedback' => ['status' => 'available'],
                'campaigns' => ['status' => 'unavailable', 'redactions' => ['reason' => 'missing-campaign-context']],
                'summary' => [
                    'read_only' => true,
                    'mutates_state' => false,
                ],
            ],
        ],
        exitCode: 1,
    );

    expect($command->capturedOutput)->toContain('Unknown lifecycle scenario')
        ->and($command->capturedOutput)->toContain('Integrations:')
        ->and($command->capturedOutput)->toContain('  Journal: unavailable (missing-code)')
        ->and($command->capturedOutput)->toContain('  Campaigns: unavailable (missing-campaign-context)');
});
