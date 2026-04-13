<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Contracts\VoucherAccessContract;

class PreparePayCodeRedemptionFlowCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'xchange:claim:start
        {code : Pay code or voucher code}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON or output}';

    protected $description = 'Prepare the normalized redemption flow metadata for a Pay Code.';

    public function handle(PreparePayCodeRedemptionFlow $action, VoucherAccessContract $vouchers): int
    {
        $voucher = $vouchers->findByCodeOrFail((string) $this->argument('code'));

        $result = $action->handle($voucher);

        $this->renderPayload($result->toArray(), 'Pay Code claim flow prepared successfully.');

        return self::SUCCESS;
    }
}
