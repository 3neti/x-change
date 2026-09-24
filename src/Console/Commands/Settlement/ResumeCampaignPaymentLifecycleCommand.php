<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Settlement;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Campaigns\AdvanceCampaignPaymentLifecycleJob;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

final class ResumeCampaignPaymentLifecycleCommand extends Command
{
    protected $signature = 'x-change:campaigns:resume-payment
        {recognition : Exact persisted campaign payment recognition reference}
        {--dispatch : Queue the existing lifecycle processor; otherwise inspect only}';

    protected $description = 'Inspect or resume one recognized Campaign QR payment without repeating payment collection.';

    public function handle(): int
    {
        $recognition = CampaignPaymentRecognition::query()
            ->with(['provisionalCoverage.completionPayCodeIssuance.voucher', 'canonicalObservation'])
            ->where('reference', (string) $this->argument('recognition'))
            ->whereNotNull('campaign_payment_qr_binding_id')
            ->first();

        if ($recognition === null) {
            $this->error('Campaign QR payment recognition not found.');

            return self::FAILURE;
        }

        if ($this->option('dispatch')) {
            AdvanceCampaignPaymentLifecycleJob::dispatch($recognition->reference)->afterCommit();
        }

        $issuance = $recognition->provisionalCoverage?->completionPayCodeIssuance;
        $delivery = FeedbackDeliveryRecord::query()
            ->where('correlation_id', 'campaign-payment-completion:'.$recognition->reference)
            ->oldest('id')
            ->first(['status', 'provider_status', 'created_at', 'last_attempted_at', 'delivered_at']);
        $submittedAt = in_array($delivery?->status, ['sent', 'delivered'], true)
            ? $delivery->last_attempted_at : null;

        $this->line(json_encode([
            'recognition_reference' => $recognition->reference,
            'dispatch_requested' => (bool) $this->option('dispatch'),
            'coverage_reference' => $recognition->provisionalCoverage?->reference,
            'completion_pay_code' => $recognition->provisionalCoverage?->completionPayCodeIssuance?->voucher?->code,
            'sms_delivery' => $delivery?->status ?? 'not_queued',
            'observation_source' => $recognition->canonicalObservation?->verification_source,
            'webhook_receipt_present' => $recognition->canonicalObservation?->webhook_receipt_id !== null,
            'timeline' => [
                'payment_settled_at' => $recognition->settled_at?->toIso8601String(),
                'payment_observed_at' => $recognition->canonicalObservation?->created_at?->toIso8601String(),
                'payment_recognized_at' => $recognition->recognized_at?->toIso8601String(),
                'pay_code_issued_at' => $issuance?->issued_at?->toIso8601String(),
                'sms_queued_at' => $delivery?->created_at?->toIso8601String(),
                'sms_submitted_at' => $submittedAt?->toIso8601String(),
                'sms_delivered_at' => $delivery?->delivered_at?->toIso8601String(),
                'sms_provider_status' => $delivery?->provider_status,
            ],
            'latency_seconds' => [
                'settlement_to_recognition' => $this->secondsBetween($recognition->settled_at, $recognition->recognized_at),
                'recognition_to_issuance' => $this->secondsBetween($recognition->recognized_at, $issuance?->issued_at),
                'sms_queue_to_submission' => $this->secondsBetween($delivery?->created_at, $submittedAt),
                'settlement_to_sms_submission' => $this->secondsBetween($recognition->settled_at, $submittedAt),
            ],
            'timing_note' => 'Submission is not handset delivery. Missing or reversed timestamps produce null durations. Detection time includes provider availability, scheduling, and queue delay; it does not isolate them.',
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function secondsBetween(?CarbonInterface $start, ?CarbonInterface $end): ?float
    {
        if ($start === null || $end === null || $end->lessThan($start)) {
            return null;
        }

        return round($start->diffInSeconds($end), 3);
    }
}
