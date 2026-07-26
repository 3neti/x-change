<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Funding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Funding\PayApprovedFundingRequest;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestWorkflowPublisher;
use Throwable;

final class PayApprovedFundingRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [1, 5, 15, 30];

    public function __construct(
        public readonly string $fundingRequestReference,
    ) {
        $this->onQueue('x-change-funding');
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
        ];
    }

    public function uniqueId(): string
    {
        return 'reviewed-funding-payment:'.$this->fundingRequestReference;
    }

    public function handle(PayApprovedFundingRequest $pay): void
    {
        $fundingRequest = FundingRequest::query()
            ->with('voucher')
            ->where('reference', $this->fundingRequestReference)
            ->sole();

        if ($fundingRequest->status === FundingRequestStatus::Completed) {
            return;
        }

        $pay->handle($fundingRequest->voucher);
    }

    public function failed(Throwable $exception): void
    {
        $fundingRequest = DB::transaction(function () use ($exception): ?FundingRequest {
            $locked = FundingRequest::query()
                ->lockForUpdate()
                ->where('reference', $this->fundingRequestReference)
                ->first();

            if (
                ! $locked instanceof FundingRequest
                || $locked->status !== FundingRequestStatus::PayCodeIssued
            ) {
                return $locked;
            }

            $nextVersion = $locked->version + 1;
            $locked->forceFill(['version' => $nextVersion])->saveQuietly();
            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'reviewed_funding_payment_failed',
                'from_status' => FundingRequestStatus::PayCodeIssued,
                'to_status' => FundingRequestStatus::PayCodeIssued,
                'actor_type' => self::class,
                'actor_id' => 'system',
                'metadata' => [
                    'retryable' => true,
                    'failure_class' => $exception::class,
                ],
                'occurred_at' => now(),
            ]);
            $locked->notices()->firstOrCreate(
                [
                    'recipient_type' => $locked->requester_type,
                    'recipient_id' => $locked->requester_id,
                    'notice_type' => 'reviewed_funding_payment_retry_required',
                ],
                [
                    'title' => 'Account Funding still pending',
                    'message' => 'System Treasury payment requires an operator retry.',
                    'action' => [
                        'type' => 'view_reviewed_funding_pay_code',
                        'funding_request_reference' => $locked->reference,
                    ],
                ],
            );

            return $locked->refresh();
        }, 3);

        if ($fundingRequest instanceof FundingRequest) {
            app(FundingRequestWorkflowPublisher::class)->publish($fundingRequest);
        }
    }
}
