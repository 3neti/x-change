<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleResultRenderer;

it('renders json payload when json option is enabled', function () {
    $command = new class extends Command {
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
    $command = new class extends Command {
        public array $output = [];

        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void
        {
            $this->output[] = $string;
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->output[] = $string;
        }

        public function newLine($count = 1): void
        {
            $this->output[] = '';
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
        ],
        exitCode: 0,
    );

    expect($exitCode)->toBe(0)
        ->and($command->output)->toContain('Lifecycle scenario completed.')
        ->and($command->output)->toContain('Scenario: basic_cash')
        ->and($command->output)->toContain('Label: Basic Cash')
        ->and($command->output)->toContain('Mode: default')
        ->and($command->output)->toContain('Voucher Code: ABCD')
        ->and($command->output)->toContain('Attempt Summary:')
        ->and($command->output)->toContain('Passed: 1')
        ->and($command->output)->toContain('Failed: 0')
        ->and($command->output)->toContain('Total: 1');
});

it('renders phase summary reconciliation and wallet transactions when present', function () {
    $command = new class extends Command {
        public array $output = [];

        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void
        {
            $this->output[] = $string;
        }

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->output[] = $string;
        }

        public function newLine($count = 1): void
        {
            $this->output[] = '';
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

    expect($command->output)->toContain('Phase Summary:')
        ->and($command->output)->toContain('Reconciliation:')
        ->and($command->output)->toContain('Recent Wallet Transactions:')
        ->and(collect($command->output)->contains(fn (string $line): bool => str_contains($line, 'provider-123')))->toBeTrue()
        ->and(collect($command->output)->contains(fn (string $line): bool => str_contains($line, 'deposit')))->toBeTrue();
});
