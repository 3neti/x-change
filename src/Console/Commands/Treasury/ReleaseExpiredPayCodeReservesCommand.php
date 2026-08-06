<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Treasury\ReleaseExpiredPayCodeReserve;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use Throwable;

final class ReleaseExpiredPayCodeReservesCommand extends Command
{
    protected $signature = 'xchange:pay-codes:release-expired
        {--limit= : Maximum number of expired Pay Codes to release}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Return eligible unclaimed expired Pay Code principal to Client Funds.';

    public function handle(ReleaseExpiredPayCodeReserve $release): int
    {
        $configuredLimit = max(
            1,
            (int) config('x-change.treasury.expiry_release.scheduled_batch_size', 100),
        );
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit === null
            ? $configuredLimit
            : min($configuredLimit, max(1, (int) $requestedLimit));
        $results = [];
        $released = 0;
        $replayed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($this->eligiblePayCodes($limit)->get() as $voucher) {
            try {
                $result = $release->handle($voucher);
                $result->replayed ? $replayed++ : $released++;
                $results[] = [
                    'pay_code' => $voucher->code,
                    ...$result->toArray(),
                ];
            } catch (TreasuryConfigurationException $exception) {
                $skipped++;
                $results[] = [
                    'pay_code' => $voucher->code,
                    'status' => 'skipped',
                    'reason' => $exception->getMessage(),
                ];
            } catch (Throwable $exception) {
                report($exception);
                $errors++;
                $results[] = [
                    'pay_code' => $voucher->code,
                    'status' => 'error',
                    'reason' => 'The expiry release could not be completed safely.',
                ];
            }
        }

        $payload = [
            'processed' => count($results),
            'released' => $released,
            'replayed' => $replayed,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
        ];

        if ($this->option('json')) {
            $flags = JSON_UNESCAPED_SLASHES;

            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->line((string) json_encode($payload, $flags));
        } else {
            $this->components->info(sprintf(
                'Processed %d expired Pay Code(s): %d released, %d replayed, %d skipped, %d error(s).',
                $payload['processed'],
                $released,
                $replayed,
                $skipped,
                $errors,
            ));
        }

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Builder<Voucher>
     */
    private function eligiblePayCodes(int $limit): Builder
    {
        return Voucher::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('redeemed_at')
            ->whereNotIn('state', [
                VoucherState::CLOSED->value,
                VoucherState::CANCELLED->value,
            ])
            ->where(
                'metadata->treasury->pay_code_reservation->status',
                'reserved',
            )
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('metadata->treasury->pay_code_reservation->source_position_purpose')
                    ->orWhere(
                        'metadata->treasury->pay_code_reservation->source_position_purpose',
                        TreasuryPositionPurpose::ClientFunds->value,
                    );
            })
            ->whereNotIn(
                'id',
                VoucherClaim::query()->select('voucher_id'),
            )
            ->whereNotIn(
                'id',
                DisbursementReconciliation::query()->select('voucher_id'),
            )
            ->oldest('expires_at')
            ->oldest('id')
            ->limit($limit);
    }
}
