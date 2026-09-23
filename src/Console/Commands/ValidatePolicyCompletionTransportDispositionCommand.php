<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportDispositionData;
use LBHurtado\XChange\Services\Settlement\PolicyCompletionTransportDispositionFileRepository;
use Throwable;

final class ValidatePolicyCompletionTransportDispositionCommand extends Command
{
    protected $signature = 'x-change:policy-completion-transport:validate
        {driver : Expected policy completion driver ID}
        {version : Expected policy completion driver version}
        {--path=policy-completion-transport.yaml : Local YAML disposition path}
        {--json : Output a redacted JSON result}';

    protected $description = 'Validate an offline policy completion transport disposition without activating it.';

    public function handle(PolicyCompletionTransportDispositionFileRepository $files): int
    {
        $driverId = trim((string) $this->argument('driver'));
        $driverVersion = trim((string) $this->argument('version'));

        try {
            $manifest = $files->read((string) $this->option('path'));
            $disposition = PolicyCompletionTransportDispositionData::fromManifest(
                $manifest,
                $driverId,
                $driverVersion,
            );
        } catch (Throwable) {
            return $this->failure($driverId, $driverVersion);
        }

        $result = [
            'success' => true,
            'schema' => PolicyCompletionTransportDispositionData::SCHEMA,
            'driver_id' => $driverId,
            'driver_version' => $driverVersion,
            'disposition_fingerprint' => $disposition->fingerprint(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Policy completion transport disposition is valid.');
            $this->line('Disposition fingerprint: '.$disposition->fingerprint());
        }

        return self::SUCCESS;
    }

    private function failure(string $driverId, string $driverVersion): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'success' => false,
                'schema' => PolicyCompletionTransportDispositionData::SCHEMA,
                'driver_id' => $driverId,
                'driver_version' => $driverVersion,
                'message' => 'Policy completion transport disposition validation failed.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error('Policy completion transport disposition validation failed.');
        }

        return self::FAILURE;
    }
}
