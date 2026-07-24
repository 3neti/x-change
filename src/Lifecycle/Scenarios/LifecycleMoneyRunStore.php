<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\LifecycleMoneyRun;
use RuntimeException;

final class LifecycleMoneyRunStore
{
    public function begin(
        string $scenarioKey,
        string $runReference,
        Model $issuer,
        string $provider,
        int $amountMinor,
        string $currency,
    ): LifecycleMoneyRun {
        $runReferenceHash = $this->runReferenceHash($runReference);
        $fingerprint = $this->fingerprint(
            $scenarioKey,
            $issuer,
            $provider,
            $amountMinor,
            $currency,
        );
        $run = LifecycleMoneyRun::query()->firstOrCreate(
            ['run_reference_hash' => $runReferenceHash],
            [
                'scenario_key' => $scenarioKey,
                'run_fingerprint' => $fingerprint,
                'issuer_type' => $issuer->getMorphClass(),
                'issuer_id' => $issuer->getKey(),
                'provider_code' => mb_strtolower(trim($provider)),
                'amount_minor' => $amountMinor,
                'currency' => mb_strtoupper(trim($currency)),
                'status' => 'started',
                'started_at' => now(),
            ],
        );

        if (! hash_equals($run->run_fingerprint, $fingerprint)) {
            throw new RuntimeException(
                'The lifecycle run reference is already bound to different money-movement parameters.',
            );
        }

        return $run;
    }

    public function attachVoucher(LifecycleMoneyRun $run, int $voucherId): LifecycleMoneyRun
    {
        if ($run->voucher_id !== null && $run->voucher_id !== $voucherId) {
            throw new RuntimeException(
                'The lifecycle money run is already bound to a different Pay Code.',
            );
        }

        $run->forceFill([
            'voucher_id' => $voucherId,
            'status' => 'pay_code_issued',
        ])->save();

        return $run->refresh();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function complete(
        LifecycleMoneyRun $run,
        array $result,
        string $status = 'completed',
    ): LifecycleMoneyRun {
        $run->forceFill([
            'status' => $status,
            'result_summary' => $result,
            'failure_reason' => null,
            'completed_at' => now(),
        ])->save();

        return $run->refresh();
    }

    public function fail(LifecycleMoneyRun $run, string $reason): LifecycleMoneyRun
    {
        $run->forceFill([
            'status' => 'failed',
            'failure_reason' => str($reason)->squish()->limit(191)->toString(),
            'completed_at' => now(),
        ])->save();

        return $run->refresh();
    }

    public function lockName(string $runReference): string
    {
        return 'x-change:lifecycle:money-run:'.$this->runReferenceHash($runReference);
    }

    private function runReferenceHash(string $runReference): string
    {
        $runReference = trim($runReference);

        if ($runReference === '') {
            throw new RuntimeException('A lifecycle money run reference is required.');
        }

        return hash_hmac('sha256', $runReference, $this->hashKey());
    }

    private function fingerprint(
        string $scenarioKey,
        Model $issuer,
        string $provider,
        int $amountMinor,
        string $currency,
    ): string {
        return hash('sha256', implode('|', [
            trim($scenarioKey),
            $issuer->getMorphClass(),
            (string) $issuer->getKey(),
            mb_strtolower(trim($provider)),
            (string) $amountMinor,
            mb_strtoupper(trim($currency)),
        ]));
    }

    private function hashKey(): string
    {
        $key = trim((string) config('app.key'));

        if ($key === '') {
            throw new RuntimeException(
                'APP_KEY is required to protect lifecycle run references.',
            );
        }

        return $key;
    }
}
