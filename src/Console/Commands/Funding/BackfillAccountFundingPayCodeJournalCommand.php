<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Funding\AccountFundingPayCodeJournalBackfill;
use RuntimeException;
use Throwable;

final class BackfillAccountFundingPayCodeJournalCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:funding:backfill-pay-code-journal
        {voucher : Numeric Voucher ID; do not provide the raw Pay Code}
        {--commit : Append the missing journal events}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Guardedly backfill missing Account Funding Pay Code claim journal events';

    public function handle(
        AccountFundingPayCodeJournalBackfill $backfill,
    ): int {
        try {
            $voucherId = $this->voucherId();
            $result = (bool) $this->option('commit')
                ? $backfill->backfill($voucherId)
                : $backfill->inspect($voucherId);

            $this->renderPayload(
                $result,
                (bool) $this->option('commit')
                    ? 'Account Funding Pay Code journal repair'
                    : 'Account Funding Pay Code journal repair preview',
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.account-funding-pay-code-journal-backfill.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'committed' => false,
                'provider_calls' => false,
                'treasury_changed' => false,
            ]);

            return self::FAILURE;
        }
    }

    private function voucherId(): int
    {
        $value = trim((string) $this->argument('voucher'));

        if (
            $value === ''
            || preg_match('/^[1-9][0-9]*$/', $value) !== 1
        ) {
            throw new RuntimeException(
                'A positive numeric Voucher ID is required; do not provide the raw Pay Code.',
            );
        }

        return (int) $value;
    }
}
