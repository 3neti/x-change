<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Funding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Actions\Funding\CheckFundingRequestTransfer;
use LBHurtado\XChange\Actions\Operations\RecordExternalJobFailure;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use Throwable;

final class ReconcileBankTransferFundingRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasSafeQueueTags;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public const string Queue = 'x-change-funding';

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $fundingRequestId)
    {
        $this->uniqueFor = max(1, (int) config(
            'x-change.funding.requests.bank_transfer.reconciliation_lock_seconds',
            120,
        ));
        $this->onQueue(self::Queue);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(5)
                ->expireAfter($this->uniqueFor)
                ->shared(),
            new RateLimited('x-change-provider-verification'),
        ];
    }

    public function uniqueId(): string
    {
        return 'bank-transfer-funding-request:'.$this->fundingRequestId;
    }

    public function handle(CheckFundingRequestTransfer $check): void
    {
        $request = FundingRequest::query()->find($this->fundingRequestId);

        if (! $request instanceof FundingRequest
            || $request->funding_type !== FundingRequestType::BankTransfer
            || ! in_array($request->status, [
                FundingRequestStatus::Submitted,
                FundingRequestStatus::UnderReview,
            ], true)) {
            return;
        }

        $check->handle($request);
    }

    public function failed(Throwable $exception): void
    {
        app(RecordExternalJobFailure::class)->handle(
            jobType: class_basename(self::class),
            subjectType: 'funding_request',
            subjectId: $this->fundingRequestId,
            failure: $exception,
            providerCode: (string) config(
                'x-change.funding.requests.bank_transfer.provider',
                'netbank',
            ),
            trigger: 'schedule',
        );
    }
}
