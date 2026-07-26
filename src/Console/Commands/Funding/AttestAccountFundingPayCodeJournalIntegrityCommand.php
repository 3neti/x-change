<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Funding\AccountFundingPayCodeJournalIntegrityAttestation;
use RuntimeException;
use Throwable;

final class AttestAccountFundingPayCodeJournalIntegrityCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:funding:attest-pay-code-journal-integrity
        {--voucher=* : Numeric Voucher IDs; never provide raw Pay Codes}
        {--authorization-reference= : Stable reference authorizing this legacy exception}
        {--commit : Append integrity-exception attestations}
        {--confirm-append-only-exception : Confirm the original entries remain unchanged and unverified}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Prove and attest legacy Account Funding journal timestamp precision loss';

    public function handle(
        AccountFundingPayCodeJournalIntegrityAttestation $attestation,
    ): int {
        try {
            $voucherIds = $this->voucherIds();
            $commit = (bool) $this->option('commit');

            if (
                $commit
                && ! (bool) $this->option(
                    'confirm-append-only-exception',
                )
            ) {
                throw new RuntimeException(
                    'Commit requires --confirm-append-only-exception because the original mismatch remains immutable.',
                );
            }

            $result = $commit
                ? $attestation->attest(
                    $voucherIds,
                    (string) $this->option(
                        'authorization-reference',
                    ),
                )
                : $attestation->inspect($voucherIds);

            $this->renderPayload(
                $result,
                $commit
                    ? 'Account Funding journal integrity attestation'
                    : 'Account Funding journal integrity attestation preview',
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.account-funding-pay-code-integrity-attestation.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'committed' => false,
                'original_entries_unchanged' => true,
                'provider_calls' => false,
                'treasury_changed' => false,
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @return list<int>
     */
    private function voucherIds(): array
    {
        $values = $this->option('voucher');

        if (! is_array($values) || $values === []) {
            throw new RuntimeException(
                'At least one --voucher option is required; never provide a raw Pay Code.',
            );
        }

        $voucherIds = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if (
                $value === ''
                || preg_match('/^[1-9][0-9]*$/', $value) !== 1
            ) {
                throw new RuntimeException(
                    'Every --voucher value must be a positive numeric Voucher ID; never provide a raw Pay Code.',
                );
            }

            $voucherIds[] = (int) $value;
        }

        return array_values(array_unique($voucherIds));
    }
}
