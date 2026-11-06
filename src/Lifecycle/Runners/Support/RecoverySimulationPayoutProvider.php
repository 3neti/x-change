<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LogicException;

/** A guarded, deterministic provider for isolated recovery acceptance only. */
final readonly class RecoverySimulationPayoutProvider implements PayoutProvider
{
    public function __construct(private Application $application) {}

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        $this->assertIsolated();
        $payout = DisbursementReconciliation::query()->where('voucher_code', $request->external_code)
            ->where('claim_type', 'value_flow')->where('meta->submission_reference', $request->reference)->sole();
        $retry = data_get($payout->meta, 'recovery.original_payout_id') !== null;
        $transactionId = ($retry ? 'SIM-OK-' : 'SIM-FAIL-').substr(hash('sha256', $request->reference), 0, 24);

        return new PayoutResultData(
            transaction_id: $transactionId, uuid: $transactionId,
            status: $retry ? PayoutStatus::COMPLETED : PayoutStatus::FAILED,
            provider: $payout->provider, metadata: ['simulation' => true],
        );
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        $this->assertIsolated();
        if (! str_starts_with($transactionId, 'SIM-FAIL-') && ! str_starts_with($transactionId, 'SIM-OK-')) {
            throw new LogicException('The simulated provider cannot inspect a real transaction.');
        }
        $payout = DisbursementReconciliation::query()->where('provider_transaction_id', $transactionId)
            ->where('claim_type', 'value_flow')->sole();

        return new PayoutResultData(
            transaction_id: $transactionId, uuid: $transactionId,
            status: str_starts_with($transactionId, 'SIM-FAIL-') ? PayoutStatus::FAILED : PayoutStatus::COMPLETED,
            provider: $payout->provider, metadata: ['simulation' => true],
        );
    }

    public function getRailFee(SettlementRail $rail): int
    {
        $this->assertIsolated();

        return 0;
    }

    private function assertIsolated(): void
    {
        $database = DB::connection()->getDatabaseName();
        $configured = config('x-change.recovery_simulation.database');
        $memoryTest = $this->application->environment('testing') && $database === ':memory:' && $configured === ':memory:';
        $dedicatedFile = is_string($database) && is_string($configured) && $database === $configured
            && str_starts_with(basename($database), 'xchange-recovery-simulation-') && str_ends_with($database, '.sqlite');
        if (! $this->application->environment('local', 'testing') || ! config('x-change.recovery_simulation.enabled', false)
            || DB::connection()->getDriverName() !== 'sqlite' || (! $memoryTest && ! $dedicatedFile)) {
            throw new LogicException('Payout recovery simulation requires an explicitly configured isolated database.');
        }
    }
}
