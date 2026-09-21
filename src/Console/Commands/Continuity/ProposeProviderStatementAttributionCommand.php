<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Continuity;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Continuity\ProposeProviderStatementAttribution;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use Throwable;

final class ProposeProviderStatementAttributionCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:continuity:propose-provider-attribution
        {--statement= : Absolute path to the private normalized provider statement CSV}
        {--capture-manifest= : Absolute path to its private capture manifest}
        {--connection= : Explicit active Treasury connection to inspect}
        {--expected-statement-sha256= : Exact statement checksum returned by capture}
        {--destination= : Stable destination instance identifier}
        {--json : Emit a sanitized machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Build a deterministic review proposal from private provider evidence without applying attribution';

    protected $help = <<<'HELP'
Build a deterministic, checksum-pinned review proposal from a captured provider
statement and its manifest. Output contains aggregate classifications and hashed
evidence references only. It never calls the provider, attributes transactions,
reconciles balances, credits accounts, changes Treasury, or authorizes recovery.
HELP;

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->help);
    }

    public function handle(ProposeProviderStatementAttribution $proposal): int
    {
        try {
            $result = $proposal->handle(
                statementPath: (string) $this->option('statement'),
                captureManifestPath: (string) $this->option('capture-manifest'),
                connectionReference: (string) $this->option('connection'),
                expectedStatementHash: (string) $this->option('expected-statement-sha256'),
                destinationInstance: (string) $this->option('destination'),
            );
            $this->renderPayload($result, 'Provider attribution proposal');

            return self::SUCCESS;
        } catch (InvalidArgumentException|TreasuryConfigurationException $exception) {
            return $this->failure($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('The provider attribution proposal could not be built safely.');
        }
    }

    private function failure(string $message): int
    {
        $this->renderPayload([
            'schema' => 'x-change.provider-attribution-proposal.v1',
            'status' => 'rejected',
            'message' => $message,
            'apply_supported' => false,
            'read_only' => true,
            'writes_database' => false,
            'writes_storage' => false,
            'writes_cache' => false,
            'writes_journal' => false,
            'writes_treasury' => false,
            'provider_calls' => false,
            'moves_money' => false,
            'credits_accounts' => false,
            'safe_to_reconcile' => false,
        ]);

        return self::FAILURE;
    }
}
