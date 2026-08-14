<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Cockpit;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverRepository;

final class ValidateLinkPreviewDriversCommand extends Command
{
    protected $signature = 'x-change:link-preview:validate
        {--json : Output the validation report as JSON}';

    protected $description = 'Validate the package link-preview YAML drivers without making network requests.';

    public function handle(LinkPreviewDriverRepository $drivers): int
    {
        $diagnostics = $drivers->diagnostics();
        $valid = $diagnostics !== []
            && collect($diagnostics)->every(
                fn (array $diagnostic): bool => $diagnostic['valid'],
            );
        $payload = [
            'schema' => 'x-change.link-preview-driver-validation.v1',
            'valid' => $valid,
            'drivers' => $diagnostics,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->table(
                ['Driver', 'Enabled', 'Valid', 'Path', 'Error'],
                collect($diagnostics)->map(fn (array $diagnostic): array => [
                    $diagnostic['key'] ?? 'unknown',
                    $diagnostic['enabled'] ? 'yes' : 'no',
                    $diagnostic['valid'] ? 'yes' : 'no',
                    $diagnostic['path'],
                    $diagnostic['error'] ?? '',
                ])->all(),
            );
        }

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
