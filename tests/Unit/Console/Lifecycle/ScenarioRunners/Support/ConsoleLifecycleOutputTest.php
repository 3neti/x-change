<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LBHurtado\XChange\Lifecycle\Output\ConsoleLifecycleOutput;

it('forwards output calls to console command', function () {
    $command = new class extends Command {
        public array $messages = [];

        public function line($string, $style = null, $verbosity = null): void
        {
            $this->messages[] = ['line', $string];
        }

        public function info($string, $verbosity = null): void
        {
            $this->messages[] = ['info', $string];
        }

        public function warn($string, $verbosity = null): void
        {
            $this->messages[] = ['warn', $string];
        }

        public function error($string, $verbosity = null): void
        {
            $this->messages[] = ['error', $string];
        }

        public function option($key = null): mixed
        {
            return match ($key) {
                'json' => true,
                'accept-pending' => true,
                default => null,
            };
        }
    };

    $output = new ConsoleLifecycleOutput($command);

    $output->line('line message');
    $output->info('info message');
    $output->warn('warn message');
    $output->error('error message');

    expect($command->messages)->toBe([
        ['line', 'line message'],
        ['info', 'info message'],
        ['warn', 'warn message'],
        ['error', 'error message'],
    ])
        ->and($output->isJson())->toBeTrue()
        ->and($output->acceptPending())->toBeTrue();
});
