<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Onboarding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Console\Concerns\InteractsWithPayloadFiles;
use LBHurtado\XChange\Console\Concerns\ResolvesIssuerAndWalletIdentifiers;

class OpenIssuerWalletCommand extends Command
{
    use InteractsWithJsonOutput;
    use InteractsWithPayloadFiles;
    use ResolvesIssuerAndWalletIdentifiers;

    protected $signature = 'xchange:wallet:open
    {issuer? : Generic issuer lookup token}
    {--issuer-id= : Internal issuer id}
    {--mobile= : Issuer mobile for resolution fallback}
    {--email= : Issuer email for resolution fallback}
    {--external-id= : External issuer identifier}
    {--currency=PHP : Wallet currency}
    {--slug=platform : Wallet slug}
    {--label= : Optional wallet name}
    {--json : Output JSON}
    {--pretty : Pretty-print JSON or output}
    {--config= : Path to JSON payload file}';

    protected $description = 'Open or resolve the default issuer wallet.';

    public function handle(OpenIssuerWallet $action): int
    {
        $payload = $this->mergePayloads(
            $this->loadPayloadFromConfigOption(),
            $this->issuerLookupPayload(),
            [
                'wallet' => array_filter([
                    'slug' => $this->option('slug'),
                    'name' => $this->option('label'),
                    'currency' => $this->option('currency'),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        );

        $result = $action->handle($payload);

        $this->renderPayload($result->toArray(), 'Issuer wallet opened successfully.');

        return self::SUCCESS;
    }
}
