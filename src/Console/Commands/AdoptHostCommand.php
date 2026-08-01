<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Host\HostApplicationShellAdopter;
use LBHurtado\XChange\Services\Host\HostUserModelAdopter;
use Throwable;

final class AdoptHostCommand extends Command
{
    protected $signature = 'x-change:host:adopt
        {--dry-run : Inspect the host without changing it}
        {--json : Render a machine-readable result}';

    protected $description = 'Safely add x-change capabilities to the host authentication model';

    public function handle(
        HostUserModelAdopter $adopter,
        HostApplicationShellAdopter $applicationShell,
    ): int {
        try {
            $result = $adopter->adopt(commit: ! (bool) $this->option('dry-run'));
            $shellResult = $applicationShell->adopt(commit: ! (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'schema' => 'x-change.host-adoption.v1',
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        $payload = [
            'schema' => 'x-change.host-adoption.v1',
            'success' => true,
            ...$result,
            'application_shell' => $shellResult,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->components->info(match ($result['status']) {
                'adopted' => 'Host User model adopted without replacing its existing capabilities.',
                'would_adopt' => 'Host User model is compatible and would be adopted.',
                default => 'Host User model is already adopted.',
            });
            $this->components->info(match ($shellResult['status']) {
                'adopted' => 'Host application shell adopted with Cockpit navigation.',
                'would_adopt' => 'Host application shell is compatible and would be adopted.',
                default => 'Host application shell is already adopted.',
            });
        }

        return self::SUCCESS;
    }
}
