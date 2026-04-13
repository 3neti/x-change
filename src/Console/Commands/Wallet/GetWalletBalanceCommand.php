<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Wallet;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Wallet\GetWalletBalance;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Console\Concerns\InteractsWithPayloadFiles;
use LBHurtado\XChange\Console\Concerns\ResolvesIssuerAndWalletIdentifiers;

class GetWalletBalanceCommand extends Command
{
    use InteractsWithJsonOutput;
    use InteractsWithPayloadFiles;
    use ResolvesIssuerAndWalletIdentifiers;

    protected $signature = 'xchange:wallet:balance
        {issuer : Issuer identifier}
        {--mobile= : Issuer mobile for resolution fallback}
        {--email= : Issuer email for resolution fallback}
        {--external-id= : External issuer identifier}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON or output}
        {--config= : Path to JSON payload file}';

    protected $description = 'Get the current balance of an issuer wallet.';

    public function handle(GetWalletBalance $action): int
    {
        $payload = $this->mergePayloads(
            $this->loadPayloadFromConfigOption(),
            $this->issuerLookupPayload(),
        );

        $result = $action->handle($payload);

        $this->renderPayload($result->toArray(), 'Wallet balance retrieved successfully.');

        return self::SUCCESS;
    }
}
