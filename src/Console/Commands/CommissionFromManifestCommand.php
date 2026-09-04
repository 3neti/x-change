<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commissioning\CommissioningManifestCommissioner;
use Throwable;

final class CommissionFromManifestCommand extends Command
{
    protected $signature = 'x-change:commission:manifest
        {--manifest= : YAML manifest path, URL, or x-change:// URI}
        {--json : Output JSON}';

    protected $description = 'Commission app-specific onboarding Pay Codes from a YAML manifest.';

    public function handle(CommissioningManifestCommissioner $commissioner): int
    {
        $manifest = trim((string) $this->option('manifest'));

        if ($manifest === '') {
            return $this->reject('A commissioning manifest is required.');
        }

        try {
            $result = $commissioner->commission($manifest);
        } catch (Throwable $exception) {
            return $this->reject($exception->getMessage());
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $result,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->components->info('Commissioning invitation Pay Codes are ready.');
            $this->table(
                ['Role', 'Pay Code', 'Claim URL', 'Status'],
                collect($result['invitations'])->map(fn (array $invitation): array => [
                    $invitation['role'],
                    $invitation['code'],
                    $invitation['claim_url'],
                    $invitation['created'] ? 'created' : 'existing',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    private function reject(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'schema' => 'x-change.commissioning-invitations.v1',
                'success' => false,
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
