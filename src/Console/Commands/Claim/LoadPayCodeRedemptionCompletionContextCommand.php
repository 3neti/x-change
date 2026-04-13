<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Redemption\LoadPayCodeRedemptionCompletionContext;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Contracts\VoucherAccessContract;

class LoadPayCodeRedemptionCompletionContextCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'xchange:claim:complete-context
        {code : Pay code or voucher code}
        {--flow-id= : Flow identifier}
        {--reference-id= : Reference identifier}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON or output}';

    protected $description = 'Load the normalized redemption completion context for a Pay Code.';

    public function handle(LoadPayCodeRedemptionCompletionContext $action, VoucherAccessContract $vouchers): int
    {
        $voucher = $vouchers->findByCodeOrFail((string) $this->argument('code'));

        $result = $action->handle(
            $voucher,
            $this->option('reference-id') ?: null,
            $this->option('flow-id') ?: null,
        );

        $this->renderPayload($result->toArray(), 'Pay Code completion context loaded successfully.');

        return self::SUCCESS;
    }
}
