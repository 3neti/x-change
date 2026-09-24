<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Settlement;

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
            ->with('provisionalCoverage.completionPayCodeIssuance.voucher')
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

        $this->line(json_encode([
            'recognition_reference' => $recognition->reference,
            'dispatch_requested' => (bool) $this->option('dispatch'),
            'coverage_reference' => $recognition->provisionalCoverage?->reference,
            'completion_pay_code' => $recognition->provisionalCoverage?->completionPayCodeIssuance?->voucher?->code,
            'sms_delivery' => FeedbackDeliveryRecord::query()
                ->where('correlation_id', 'campaign-payment-completion:'.$recognition->reference)
                ->value('status') ?? 'not_queued',
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
