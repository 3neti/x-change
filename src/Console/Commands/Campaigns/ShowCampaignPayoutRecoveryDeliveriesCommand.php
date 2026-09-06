<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Campaigns;

use Illuminate\Console\Command;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Jobs\Campaigns\ConvergeCampaignFeedbackDeliveryJob;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use RuntimeException;
use Throwable;

final class ShowCampaignPayoutRecoveryDeliveriesCommand extends Command
{
    protected $signature = 'x-change:campaigns:payout-recovery-deliveries
        {--authorization= : Campaign worksheet authorization reference}
        {--attempt= : Campaign delivery attempt reference}
        {--pay-code= : Recovery Pay Code}
        {--process-job= : Process one exact queued campaign recovery job ID}
        {--confirm-send : Confirm the selected job may contact the configured SMS provider}
        {--limit=25 : Maximum attempts to display}
        {--json : Output JSON}';

    protected $description = 'Inspect campaign payout recovery delivery attempts and optionally process one exact queued recovery job.';

    public function handle(): int
    {
        try {
            $processed = $this->processJobIfRequested();
            $rows = $this->attemptRows();
            $payload = [
                'processed_job' => $processed,
                'attempts' => $rows,
                'pending_jobs' => $this->pendingJobs($rows),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->renderFailure($exception);
        }

        return $this->renderSuccess($payload);
    }

    /** @return array<string, mixed>|null */
    private function processJobIfRequested(): ?array
    {
        $jobId = $this->option('process-job');

        if (! is_scalar($jobId) || trim((string) $jobId) === '') {
            return null;
        }

        if (! $this->option('confirm-send')) {
            throw new RuntimeException('Processing a recovery delivery job requires --confirm-send.');
        }

        $record = DB::table('jobs')
            ->where('id', (int) $jobId)
            ->where('queue', DispatchCampaignFeedbackJob::Queue)
            ->first();

        if (! $record) {
            throw new RuntimeException('The requested recovery delivery job was not found on the x-change-feedback queue.');
        }

        $queuedJob = $this->queuedJob($record);
        $this->assertRecoveryJobMatchesFilters($queuedJob);

        DB::table('jobs')
            ->where('id', (int) $record->id)
            ->update([
                'reserved_at' => time(),
                'attempts' => ((int) $record->attempts) + 1,
            ]);

        $record = DB::table('jobs')->where('id', (int) $record->id)->first();
        $queue = app('queue')->connection('database');
        $job = new DatabaseJob(
            app(),
            $queue,
            new DatabaseJobRecord((object) $record),
            'database',
            DispatchCampaignFeedbackJob::Queue,
        );
        $job->fire();

        if (! $job->isDeletedOrReleased()) {
            $job->delete();
        }

        return [
            'id' => (int) $record->id,
            'class' => $queuedJob::class,
            'deleted' => $job->isDeleted(),
            'released' => $job->isReleased(),
            'failed' => $job->hasFailed(),
        ];
    }

    private function queuedJob(object $record): object
    {
        $payload = json_decode((string) $record->payload, true, flags: JSON_THROW_ON_ERROR);
        $class = (string) data_get($payload, 'data.commandName');

        if (! in_array($class, [
            DispatchCampaignFeedbackJob::class,
            DeliverQueuedFeedbackSmsJob::class,
            ConvergeCampaignFeedbackDeliveryJob::class,
        ], true)) {
            throw new RuntimeException('The queued job is not part of campaign recovery delivery.');
        }

        $command = data_get($payload, 'data.command');
        if (! is_string($command) || trim($command) === '') {
            throw new RuntimeException('The queued job payload is incomplete.');
        }

        $job = unserialize(Crypt::decrypt($command), ['allowed_classes' => true]);
        if (! $job instanceof $class) {
            throw new RuntimeException('The queued job payload does not match its declared class.');
        }

        return $job;
    }

    private function assertRecoveryJobMatchesFilters(object $job): void
    {
        $attempt = match (true) {
            $job instanceof DispatchCampaignFeedbackJob => CampaignDeliveryAttempt::query()
                ->with(['fulfillment', 'events'])
                ->findOrFail($job->attemptId),
            $job instanceof ConvergeCampaignFeedbackDeliveryJob => CampaignDeliveryAttempt::query()
                ->with(['fulfillment', 'events'])
                ->findOrFail($job->attemptId),
            $job instanceof DeliverQueuedFeedbackSmsJob => $this->attemptForDeliveryId($job->deliveryId),
            default => null,
        };

        if (! $attempt instanceof CampaignDeliveryAttempt
            || data_get($attempt->metadata, 'purpose') !== 'beneficiary_payout_recovery') {
            throw new RuntimeException('The selected job is not tied to a campaign payout recovery attempt.');
        }

        $attemptFilter = $this->stringOption('attempt');
        if ($attemptFilter !== null && $attempt->reference !== $attemptFilter) {
            throw new RuntimeException('The selected job does not match the requested delivery attempt.');
        }

        $authorizationFilter = $this->stringOption('authorization');
        if ($authorizationFilter !== null && $attempt->authorization?->reference !== $authorizationFilter) {
            throw new RuntimeException('The selected job does not match the requested authorization.');
        }

        $payCodeFilter = $this->stringOption('pay-code');
        if ($payCodeFilter !== null && $attempt->fulfillment?->pay_code !== $payCodeFilter) {
            throw new RuntimeException('The selected job does not match the requested Pay Code.');
        }
    }

    private function attemptForDeliveryId(string $deliveryId): ?CampaignDeliveryAttempt
    {
        $event = DB::table('x_change_campaign_delivery_attempt_events')
            ->where('metadata->feedback_delivery_id', $deliveryId)
            ->orderByDesc('id')
            ->first();

        if (! $event) {
            return null;
        }

        return CampaignDeliveryAttempt::query()
            ->with(['authorization', 'fulfillment', 'events'])
            ->find((int) $event->campaign_delivery_attempt_id);
    }

    /** @return list<array<string, mixed>> */
    private function attemptRows(): array
    {
        return CampaignDeliveryAttempt::query()
            ->with(['authorization', 'fulfillment', 'events'])
            ->when($this->stringOption('authorization'), function ($query, string $reference): void {
                $query->whereHas('authorization', fn ($authorization) => $authorization->where('reference', $reference));
            })
            ->when($this->stringOption('attempt'), fn ($query, string $reference) => $query->where('reference', $reference))
            ->when($this->stringOption('pay-code'), function ($query, string $code): void {
                $query->whereHas('fulfillment', fn ($fulfillment) => $fulfillment->where('pay_code', $code));
            })
            ->where('metadata->purpose', 'beneficiary_payout_recovery')
            ->latest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->map(fn (CampaignDeliveryAttempt $attempt): array => $this->attemptRow($attempt))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function attemptRow(CampaignDeliveryAttempt $attempt): array
    {
        $terminal = $attempt->events
            ->first(fn ($event): bool => in_array($event->event_type, CampaignDeliveryAttempt::TerminalEventTypes, true));
        $latest = $attempt->events->sortByDesc('sequence')->first();

        return [
            'attempt_reference' => (string) $attempt->reference,
            'authorization_reference' => $attempt->authorization?->reference,
            'pay_code' => $attempt->fulfillment?->pay_code,
            'channel' => $attempt->channel,
            'attempt_number' => $attempt->attempt_number,
            'status' => $terminal?->event_type ?? $latest?->event_type ?? 'unknown',
            'safe_error_code' => $terminal?->safe_error_code ?? $latest?->safe_error_code,
            'provider_status' => $terminal?->provider_status ?? $latest?->provider_status,
            'feedback_delivery_id' => $this->feedbackDeliveryId($attempt),
            'requested_at' => optional($attempt->requested_at)->toJSON(),
            'events' => $attempt->events
                ->map(fn ($event): array => [
                    'sequence' => $event->sequence,
                    'event_type' => $event->event_type,
                    'safe_error_code' => $event->safe_error_code,
                    'provider_status' => $event->provider_status,
                    'occurred_at' => optional($event->occurred_at)->toJSON(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function feedbackDeliveryId(CampaignDeliveryAttempt $attempt): ?string
    {
        return $attempt->events
            ->pluck('metadata')
            ->map(fn (mixed $metadata): ?string => is_array($metadata)
                ? $this->stringValue($metadata['feedback_delivery_id'] ?? null)
                : null)
            ->filter()
            ->last();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function pendingJobs(array $rows): array
    {
        $attempts = collect($rows)->pluck('attempt_reference')->filter()->values();
        $deliveryIds = collect($rows)->pluck('feedback_delivery_id')->filter()->values();

        if ($attempts->isEmpty() && $deliveryIds->isEmpty()) {
            return [];
        }

        return DB::table('jobs')
            ->where('queue', DispatchCampaignFeedbackJob::Queue)
            ->orderBy('id')
            ->get(['id', 'queue', 'payload'])
            ->map(fn (object $record): ?array => $this->pendingJobRow($record, $attempts, $deliveryIds))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, string>  $attempts
     * @param  Collection<int, string>  $deliveryIds
     * @return array<string, mixed>|null
     */
    private function pendingJobRow(object $record, Collection $attempts, Collection $deliveryIds): ?array
    {
        try {
            $job = $this->queuedJob($record);
        } catch (Throwable) {
            return null;
        }

        $attemptId = null;
        $deliveryId = null;
        $attemptReference = null;

        if ($job instanceof DispatchCampaignFeedbackJob || $job instanceof ConvergeCampaignFeedbackDeliveryJob) {
            $attemptId = $job->attemptId;
            $attemptReference = CampaignDeliveryAttempt::query()
                ->whereKey($attemptId)
                ->value('reference');
        }

        if ($job instanceof DeliverQueuedFeedbackSmsJob) {
            $deliveryId = $job->deliveryId;
            $attemptReference = $this->attemptForDeliveryId($deliveryId)?->reference;
        }

        if (($attemptReference === null || ! $attempts->contains($attemptReference))
            && ($deliveryId === null || ! $deliveryIds->contains($deliveryId))) {
            return null;
        }

        return [
            'id' => (int) $record->id,
            'queue' => $record->queue,
            'class' => $job::class,
            'attempt_id' => $attemptId,
            'attempt_reference' => $attemptReference,
            'delivery_id' => $deliveryId,
        ];
    }

    private function stringOption(string $key): ?string
    {
        return $this->stringValue($this->option($key));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param  array<string, mixed>  $payload */
    private function renderSuccess(array $payload): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($payload['processed_job'] !== null) {
            $this->components->info(sprintf('Processed recovery queue job #%d.', $payload['processed_job']['id']));
        }

        if ($payload['attempts'] === []) {
            $this->components->info('No campaign payout recovery delivery attempts matched the filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['Attempt', 'Authorization', 'Pay Code', 'Channel', 'Status', 'Delivery ID'],
            array_map(static fn (array $row): array => [
                $row['attempt_reference'],
                $row['authorization_reference'] ?? '—',
                $row['pay_code'] ?? '—',
                $row['channel'],
                $row['status'],
                $row['feedback_delivery_id'] ?? '—',
            ], $payload['attempts']),
        );

        return self::SUCCESS;
    }

    private function renderFailure(Throwable $exception): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'success' => false,
                'error' => $exception::class,
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $this->components->error($exception->getMessage());

        return self::FAILURE;
    }
}
