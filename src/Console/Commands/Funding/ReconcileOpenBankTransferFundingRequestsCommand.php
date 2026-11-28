<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferAmountReservationStatus;
use LBHurtado\XChange\Jobs\Funding\ReconcileBankTransferFundingRequestJob;
use LBHurtado\XChange\Models\FundingRequest;

final class ReconcileOpenBankTransferFundingRequestsCommand extends Command
{
    protected $signature = 'xchange:funding:reconcile-bank-transfers
        {--limit= : Maximum Bank Transfer requests to reconcile in this run}';

    protected $description = 'Queue exact provider reconciliation for eligible Bank Transfer funding requests';

    public function handle(): int
    {
        if (! (bool) config(
            'x-change.funding.requests.bank_transfer.scheduled_reconciliation_enabled',
            true,
        )) {
            $this->components->info('Bank Transfer reconciliation is disabled; no checks were queued.');

            return self::SUCCESS;
        }

        $configuredLimit = max(1, (int) config(
            'x-change.funding.requests.bank_transfer.scheduled_reconciliation_batch_size',
            100,
        ));
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit === null
            ? $configuredLimit
            : min($configuredLimit, max(1, (int) $requestedLimit));
        $queued = 0;

        $this->eligibleRequests()
            ->limit($limit)
            ->each(function (FundingRequest $request) use (&$queued): void {
                ReconcileBankTransferFundingRequestJob::dispatch((int) $request->getKey());
                $queued++;
            });

        $this->components->info("Queued {$queued} Bank Transfer reconciliation check(s).");

        return self::SUCCESS;
    }

    /**
     * @return Builder<FundingRequest>
     */
    private function eligibleRequests(): Builder
    {
        return FundingRequest::query()
            ->where('funding_type', FundingRequestType::BankTransfer)
            ->whereIn('status', [
                FundingRequestStatus::Submitted,
                FundingRequestStatus::UnderReview,
            ])
            ->whereHas('transferAmountReservation', function (Builder $query): void {
                $query
                    ->where('status', FundingTransferAmountReservationStatus::Reserved)
                    ->where('expires_at', '>=', now());
            })
            ->with('transferAmountReservation')
            ->oldest('submitted_at')
            ->oldest('id');
    }
}
