<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Settlement\PolicyCompletionTransportDispositionFileRepository;
use Throwable;

final class GeneratePolicyCompletionTransportDispositionTemplateCommand extends Command
{
    protected $signature = 'x-change:policy-completion-transport:template
        {driver : Policy completion driver ID}
        {version : Policy completion driver version}
        {--path=policy-completion-transport.yaml : Local YAML template path}
        {--json : Output a redacted JSON result}';

    protected $description = 'Generate a disabled, unaccepted policy completion transport disposition template.';

    public function handle(PolicyCompletionTransportDispositionFileRepository $files): int
    {
        $driverId = trim((string) $this->argument('driver'));
        $driverVersion = trim((string) $this->argument('version'));

        try {
            $path = $files->writeTemplate(
                (string) $this->option('path'),
                $driverId,
                $driverVersion,
            );
        } catch (Throwable) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => false,
                    'message' => 'Policy completion transport disposition template generation failed.',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->components->error('Policy completion transport disposition template generation failed.');
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'success' => true,
                'path' => $path,
                'driver_id' => $driverId,
                'driver_version' => $driverVersion,
                'enabled' => false,
                'accepted' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("Policy completion transport disposition template created [{$path}].");
            $this->components->warn('The template is disabled and unaccepted until completed and formally approved.');
        }

        return self::SUCCESS;
    }
}
