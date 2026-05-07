<?php

declare(strict_types=1);

use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleDisbursementPoller;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleOutputContract;

it('can poll without a console command dependency', function () {
    $output = new class implements LifecycleOutputContract {
        public array $messages = [];

        public function line(string $message): void
        {
            $this->messages[] = ['line', $message];
        }

        public function info(string $message): void
        {
            $this->messages[] = ['info', $message];
        }

        public function warn(string $message): void
        {
            $this->messages[] = ['warn', $message];
        }

        public function error(string $message): void
        {
            $this->messages[] = ['error', $message];
        }

        public function isJson(): bool
        {
            return true;
        }

        public function acceptPending(): bool
        {
            return false;
        }
    };

    expect(app(LifecycleDisbursementPoller::class))->toBeInstanceOf(LifecycleDisbursementPoller::class)
        ->and($output->isJson())->toBeTrue();
});
