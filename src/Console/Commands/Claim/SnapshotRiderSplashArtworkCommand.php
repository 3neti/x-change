<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Exceptions\RiderStampArtifactUnavailable;

final class SnapshotRiderSplashArtworkCommand extends Command
{
    protected $signature = 'x-change:claim:snapshot-splash-artwork
        {code : Existing Pay Code}
        {--force : Replace an existing valid snapshot}
        {--json : Emit a machine-readable result}';

    protected $description = 'Capture trusted Rider Splash artwork and materialize its immutable Stamp image';

    public function handle(
        RiderSplashArtworkSnapshotterContract $snapshots,
        RiderStampArtifactStoreContract $artifacts,
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

        try {
            $artifact = $artifacts->materialize(
                $voucher,
                route('x-change.claim.show', ['code' => $voucher->code]),
                (bool) $this->option('force'),
            );
        } catch (RiderStampArtifactUnavailable) {
            return $this->renderResult([
                'schema' => 'x-change.rider-splash-artwork-snapshot-command.v1',
                'success' => false,
                'status' => 'artifact_unavailable',
                'pay_code' => $code,
                'snapshot' => $snapshot->toArray(),
            ], self::FAILURE);
        }

        return $this->renderResult([
            'schema' => 'x-change.rider-splash-artwork-snapshot-command.v1',
            'success' => true,
            'status' => 'ready',
            'pay_code' => $code,
            'snapshot' => $snapshot->toArray(),
            'artifact' => $artifact->toArray(),
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
            $this->info('Rider Splash artwork snapshot and immutable Stamp ready.');
        } else {
            $this->error('Rider Splash artwork snapshot unavailable.');
        }

        $this->line('Pay Code: '.($payload['pay_code'] ?? '—'));
        $this->line('Status: '.($payload['status'] ?? 'unknown'));

        return $exitCode;
    }
}
