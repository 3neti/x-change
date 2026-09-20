<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Continuity;

use Illuminate\Console\Command;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Continuity\CaptureProviderStatementSnapshot;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use Throwable;

final class CaptureProviderStatementCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:continuity:capture-provider-statement
        {--connection= : Explicit active NetBank Treasury connection}
        {--from= : Inclusive start date in YYYY-MM-DD}
        {--to= : Exclusive end date in YYYY-MM-DD}
        {--max-rows=10000 : Maximum provider rows to capture}
        {--confirm-sensitive-capture : Confirm private provider evidence may be stored}
        {--json : Emit a sanitized machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Capture a bounded private NetBank statement snapshot without reconciliation or money movement';

    protected $help = <<<'HELP'
Fetch and normalize a bounded NetBank corporate-account statement into private,
content-addressed evidence. The command never reconciles transactions, credits an
account, changes Treasury positions, writes journal events, or authorizes recovery.
HELP;

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->help);
    }

    public function handle(CaptureProviderStatementSnapshot $capture): int
    {
        try {
            $result = $capture->handle(
                connectionReference: (string) $this->option('connection'),
                from: (string) $this->option('from'),
                to: (string) $this->option('to'),
                maximumRows: filter_var($this->option('max-rows'), FILTER_VALIDATE_INT) ?: 0,
                sensitiveCaptureConfirmed: (bool) $this->option('confirm-sensitive-capture'),
            );
            $this->renderPayload($result, 'Private provider statement captured');

            return self::SUCCESS;
        } catch (InvalidArgumentException|TreasuryConfigurationException $exception) {
            return $this->failure($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('The provider statement could not be captured safely.');
        }
    }

    private function failure(string $message): int
    {
        $this->renderPayload([
            'schema' => 'x-change.provider-statement-capture.v1',
            'status' => 'rejected',
            'message' => $message,
            'provider_calls' => null,
            'writes_storage' => false,
            'writes_database' => false,
            'writes_journal' => false,
            'moves_money' => false,
            'safe_to_reconcile' => false,
        ]);

        return self::FAILURE;
    }
}
