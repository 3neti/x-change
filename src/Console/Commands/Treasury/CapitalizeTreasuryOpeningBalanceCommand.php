<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningCapitalizationConnectionData;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningCapitalizationService;
use Throwable;

final class CapitalizeTreasuryOpeningBalanceCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:treasury:capitalize-opening
        {--connection=* : Limit capitalization to explicit connection references}
        {--authorization-reference= : Stable deployment or control authorization reference}
        {--confirm-system-ownership : Confirm that opening provider funds belong to the system principal}
        {--commit : Move reconciled Legacy Unattributed value into Account Funding Reserve}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Preview or capitalize reconciled opening provider funds for system Account Funding';

    public function handle(
        TreasuryOpeningCapitalizationService $capitalization,
    ): int {
        $commit = (bool) $this->option('commit');

        try {
            $result = $capitalization->capitalize(
                connectionReferences: array_values((array) $this->option(
                    'connection',
                )),
                authorizationReference: trim((string) $this->option(
                    'authorization-reference',
                )),
                systemOwnershipConfirmed: (bool) $this->option(
                    'confirm-system-ownership',
                ),
                commit: $commit,
            );
            $connections = array_map(
                $this->connectionPayload(...),
                $result->connections,
            );
            $this->renderPayload([
                'schema' => 'x-change.treasury-opening-capitalization.v1',
                'success' => true,
                'mode' => $commit ? 'commit' : 'preview',
                'provider_calls' => true,
                'inventory_changed' => false,
                'connections' => $connections,
            ], $commit
                ? 'Treasury opening funds capitalized'
                : 'Treasury opening capitalization preview');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.treasury-opening-capitalization.v1',
                'success' => false,
                'mode' => $commit ? 'commit' : 'preview',
                'message' => $exception->getMessage(),
                'inventory_changed' => false,
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function connectionPayload(
        TreasuryOpeningCapitalizationConnectionData $connection,
    ): array {
        return [
            'connection' => $connection->connectionReference,
            'provider' => $connection->provider,
            'currency' => $connection->currency,
            'status' => $connection->status,
            'provider_balance_minor' => $connection->providerBalanceMinor,
            'inventory_balance_minor' => $connection->inventoryBalanceMinor,
            'position_balance_minor' => $connection->positionBalanceMinor,
            'capitalized_amount_minor' => $connection->capitalizedAmountMinor,
            'legacy_unattributed_before_minor' => $connection->legacyUnattributedBeforeMinor,
            'legacy_unattributed_after_minor' => $connection->legacyUnattributedAfterMinor,
            'account_funding_reserve_before_minor' => $connection->accountFundingReserveBeforeMinor,
            'account_funding_reserve_after_minor' => $connection->accountFundingReserveAfterMinor,
            'operation_reference' => $connection->operationReference,
        ];
    }
}
