<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;

final class SnapshotRiderSplashArtworkCommand extends Command
{
    protected $signature = 'x-change:claim:snapshot-splash-artwork
        {code : Existing Pay Code}
        {--force : Replace an existing valid snapshot}
        {--json : Emit a machine-readable result}';

    protected $description = 'Capture validated Rider Splash artwork for a durable claim share card';

    public function handle(
        RiderSplashArtworkSnapshotterContract $snapshots,
    ): int {
        $code = strtoupper(trim((string) $this->argument('code')));
        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher instanceof Voucher) {
            return $this->renderResult([
                'schema' => 'x-change.rider-splash-artwork-snapshot-command.v1',
                'success' => false,
                'status' => 'not_found',
                'pay_code' => $code,
            ], self::FAILURE);
        }

        $snapshot = $snapshots->capture(
            $voucher,
            (bool) $this->option('force'),
        );

        if ($snapshot === null) {
            return $this->renderResult([
                'schema' => 'x-change.rider-splash-artwork-snapshot-command.v1',
                'success' => false,
                'status' => 'unavailable',
                'pay_code' => $code,
            ], self::FAILURE);
        }

        return $this->renderResult([
            'schema' => 'x-change.rider-splash-artwork-snapshot-command.v1',
            'success' => true,
            'status' => 'ready',
            'pay_code' => $code,
            'snapshot' => $snapshot->toArray(),
            'external_artwork_fetch_possible' => true,
            'money_changed' => false,
        ], self::SUCCESS);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderResult(array $payload, int $exitCode): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $exitCode;
        }

        if ($payload['success']) {
            $this->info('Rider Splash artwork snapshot ready.');
        } else {
            $this->error('Rider Splash artwork snapshot unavailable.');
        }

        $this->line('Pay Code: '.($payload['pay_code'] ?? '—'));
        $this->line('Status: '.($payload['status'] ?? 'unknown'));

        return $exitCode;
    }
}
