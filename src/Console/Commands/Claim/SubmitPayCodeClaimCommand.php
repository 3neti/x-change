<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Concerns\InteractsWithClaimPayload;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Console\Concerns\InteractsWithPayloadFiles;
use LBHurtado\XChange\Contracts\VoucherAccessContract;

class SubmitPayCodeClaimCommand extends Command
{
    use InteractsWithClaimPayload;
    use InteractsWithJsonOutput;
    use InteractsWithPayloadFiles;

    protected $signature = 'xchange:claim:submit
        {code : Pay code or voucher code}
        {--mobile= : Redeemer mobile}
        {--country=PH : Country code}
        {--bank-code= : Destination bank code}
        {--account-number= : Destination account number}
        {--amount= : Withdrawal amount}
        {--secret= : Secret or PIN}
        {--flow-id= : Flow identifier}
        {--reference-id= : Reference identifier}
        {--payload= : Inline JSON payload override}
        {--idempotency-key= : Idempotency key}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON or output}
        {--config= : Path to JSON payload file}';

    protected $description = 'Submit a canonical Pay Code claim, routing internally to redeem or withdraw.';

    public function handle(SubmitPayCodeClaim $action, VoucherAccessContract $vouchers): int
    {
        $voucher = $vouchers->findByCodeOrFail((string) $this->argument('code'));

        $payload = $this->mergePayloads(
            $this->loadPayloadFromConfigOption(),
            $this->claimPayloadFromOptions(),
        );

        $result = $action->handle($voucher, $payload);

        $this->renderPayload($result->toArray(), 'Pay Code claim submitted successfully.');

        return self::SUCCESS;
    }
}
