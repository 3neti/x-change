<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Commissioning\CommissioningManifestPreviewer;
use Throwable;

final class PreviewCommissioningManifestCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:commission:preview
        {--manifest= : YAML manifest path, URL, or x-change:// URI}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Preview the exact provider balance and reserve facts for hardened commissioning.';

    public function handle(CommissioningManifestPreviewer $previewer): int
    {
        $manifest = trim((string) $this->option('manifest'));

        if ($manifest === '') {
            return $this->reject('A commissioning manifest is required.');
        }

        try {
            $preview = $previewer->preview($manifest);
        } catch (Throwable $exception) {
            return $this->reject($exception->getMessage());
        }

        $this->renderPayload($preview, 'Commissioning preview is ready');

        return self::SUCCESS;
    }

    private function reject(string $message): int
    {
        $this->renderPayload([
            'schema' => 'x-change.commissioning-preview.v1',
            'ready' => false,
            'mutation' => false,
            'message' => $message,
        ]);

        return self::FAILURE;
    }
}
