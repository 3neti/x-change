<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Models\FundingIntent;
use Throwable;

class VerifyFundingWebhookReceipt
{
    public function __construct(
        private readonly VerifyFundingIntent $verifyIntent,
        private readonly OpenFundingSuspenseCase $openSuspenseCase,
    ) {}

    public function handle(WebhookReceipt $receipt): int
    {
        return Cache::lock(
            'x-change:funding-webhook-verification:'.$receipt->getKey(),
            (int) config('x-change.funding.verification_lock_seconds', 120),
        )->block(
            (int) config('x-change.funding.verification_lock_wait_seconds', 5),
            fn (): int => $this->verify($receipt),
        );
    }

    private function verify(WebhookReceipt $receipt): int
    {
        $receipt = WebhookReceipt::query()->findOrFail($receipt->getKey());

        if (! $receipt->signature_verified || $receipt->authentication_status !== 'authenticated') {
            return 0;
        }

        if ($receipt->processing_status === 'processed') {
            return 0;
        }

        $receipt->forceFill([
            'processing_status' => 'verifying',
            'error_message' => null,
        ])->save();

        try {
            $intents = $this->candidateIntents($receipt);

            if ($intents->isEmpty()) {
                $this->openSuspenseCase->handle(
                    provider: $receipt->provider_code,
                    reasonCode: 'authenticated_evidence_unmatched',
                    receipt: $receipt,
                    details: [
                        'webhook_receipt_id' => $receipt->getKey(),
                    ],
                );
                $receipt->forceFill([
                    'processing_status' => 'unmatched',
                    'processed_at' => now(),
                    'error_message' => 'No active Funding Intent matched the provider evidence.',
                ])->save();

                return 0;
            }

            foreach ($intents as $intent) {
                $this->verifyIntent->handle($intent, new FundingIntentVerificationData(
                    trigger: FundingVerificationTrigger::Webhook,
                    actorId: $receipt->provider_code,
                    webhookReceiptId: $receipt->getKey(),
                ));
            }

            $receipt->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return $intents->count();
        } catch (Throwable $exception) {
            $receipt->forceFill([
                'processing_status' => 'failed',
                'error_message' => 'Funding verification failed: '.class_basename($exception),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return Collection<int, FundingIntent>
     */
    private function candidateIntents(WebhookReceipt $receipt): Collection
    {
        return FundingIntent::query()
            ->where('provider_code', $receipt->provider_code)
            ->whereIn('status', [
                FundingIntentStatus::AwaitingFunds,
                FundingIntentStatus::EvidenceReceived,
                FundingIntentStatus::Verifying,
                FundingIntentStatus::Settled,
            ])
            ->when(
                $receipt->request_id !== null,
                fn ($query) => $query->where('provider_request_id', $receipt->request_id),
            )
            ->oldest('id')
            ->limit((int) config('x-change.funding.verification_candidate_limit', 100))
            ->get();
    }
}
