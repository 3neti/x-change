<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Events\FundingRequestChanged;
use LBHurtado\XChange\Models\FundingRequest;

final class FundingRequestWorkflowPublisher
{
    public function submitted(FundingRequest $fundingRequest): void
    {
        foreach ($this->reviewerIds() as $reviewerId) {
            $fundingRequest->notices()->firstOrCreate(
                [
                    'recipient_type' => $fundingRequest->requester_type,
                    'recipient_id' => $reviewerId,
                    'notice_type' => 'funding_request_submitted',
                ],
                [
                    'title' => 'Account Funding requested',
                    'message' => 'A new Account Funding request requires verification.',
                    'action' => [
                        'type' => 'view_funding_review_queue',
                        'funding_request_reference' => $fundingRequest->reference,
                    ],
                ],
            );
        }

        $this->publish($fundingRequest);
    }

    public function publish(FundingRequest $fundingRequest): void
    {
        $recipients = collect([
            [
                'type' => $fundingRequest->requester_type,
                'id' => $fundingRequest->requester_id,
            ],
            ...array_map(
                fn (string $reviewerId): array => [
                    'type' => $fundingRequest->requester_type,
                    'id' => $reviewerId,
                ],
                $this->reviewerIds(),
            ),
        ])->unique(fn (array $recipient): string => implode("\0", $recipient));
        $requestReference = $fundingRequest->reference;
        $status = $fundingRequest->status->value;
        $version = $fundingRequest->version;
        $occurredAt = now()->toIso8601String();

        DB::afterCommit(static function () use (
            $occurredAt,
            $recipients,
            $requestReference,
            $status,
            $version,
        ): void {
            foreach ($recipients as $recipient) {
                FundingRequestChanged::dispatch(
                    $recipient['type'],
                    $recipient['id'],
                    $requestReference,
                    $status,
                    $version,
                    $occurredAt,
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function reviewerIds(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) config('x-change.funding.requests.reviewer_ids', []),
        ))));
    }
}
