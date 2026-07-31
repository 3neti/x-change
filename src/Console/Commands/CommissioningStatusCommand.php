<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

final class CommissioningStatusCommand extends Command
{
    protected $signature = 'x-change:commissioning:status {--json : Output JSON}';

    protected $description = 'Show the fail-closed X-Change commissioning state.';

    public function handle(CommissioningStateResolver $commissioning): int
    {
        $state = $commissioning->resolve();
        $payload = [
            'schema' => 'x-change.commissioning-status.v1',
            'state' => $state->state->value,
            'operational' => $state->isOperational(),
            'profile' => $state->profile,
            'reason' => $state->reason,
            'missing_variables' => $state->missingVariables,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Commissioning state: '.$state->state->value);
            $this->line('Profile: '.$state->profile);
        }

        return $state->isOperational() ? self::SUCCESS : self::FAILURE;
    }
}
