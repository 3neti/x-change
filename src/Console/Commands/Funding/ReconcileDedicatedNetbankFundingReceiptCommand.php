<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Funding\ReconcileDedicatedNetbankFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class ReconcileDedicatedNetbankFundingReceiptCommand extends Command
{
    protected $signature = 'xchange:funding:reconcile-dedicated-netbank
        {--address= : Standing Funding Address reference}
        {--transaction= : NetBank provider transaction ID}
        {--amount-minor= : Expected received amount in minor PHP units}
        {--payer-evidence-reference= : Payer receipt reference; stored only as a hash}
        {--confirm : Confirm the scoped reconciliation}';

    protected $description = 'Reconcile one verified historical NetBank receipt for an automatic dedicated account';

    public function handle(ReconcileDedicatedNetbankFundingReceipt $reconcile): int
    {
        if (! $this->option('confirm')) {
            $this->components->error('Pass --confirm only after independently verifying the provider transaction and payer evidence.');

            return self::INVALID;
        }

        $reference = trim((string) $this->option('address'));
        $transaction = trim((string) $this->option('transaction'));
        $amountMinor = (int) $this->option('amount-minor');
        $evidenceReference = trim((string) $this->option('payer-evidence-reference'));
        $address = StandingFundingAddress::query()->where('reference', $reference)->first();

        if (! $address instanceof StandingFundingAddress || $transaction === '' || $amountMinor <= 0 || $evidenceReference === '') {
            $this->components->error('Address, transaction, positive minor amount, and payer evidence reference are required.');

            return self::INVALID;
        }

        $receipt = $reconcile->handle($address, $transaction, $amountMinor, $evidenceReference);
        $this->components->info("Reconciled receipt [{$receipt->reference}] with status [{$receipt->status->value}].");

        return self::SUCCESS;
    }
}
