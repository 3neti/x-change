<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\IssuanceBatch;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

final readonly class IssuanceBatchRecoveryJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        IssuanceBatch $batch,
        string $eventType,
        Request $request,
        ?MobileVerificationChallenge $challenge = null,
        array $details = [],
    ): void {
        $diagnostics = $this->diagnostics($request);
        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'batch_reference' => $batch->reference,
            'challenge_reference' => $challenge?->reference,
            'client' => $diagnostics,
            ...$details,
        ];

        Log::info('x-change.batch_recovery', [
            'event_type' => $eventType,
            'batch_reference' => $batch->reference,
            'challenge_reference' => $challenge?->reference,
            'client' => $diagnostics,
            ...$details,
        ]);

        try {
            $this->recorder->record(new ExecutionJournalEntryData(
                eventType: $eventType,
                occurredAt: CarbonImmutable::now(),
                actor: new ExecutionActorData(
                    id: 'public',
                    type: 'public',
                    name: 'Public batch recovery',
                ),
                subject: new ExecutionSubjectData(
                    id: (string) $batch->getKey(),
                    type: 'issuance_batch',
                    display: $batch->reference,
                ),
                references: new ExecutionReferenceData(
                    correlationId: 'batch-recovery:'.$batch->reference,
                    causationId: $challenge?->reference ?? $batch->reference,
                    executionId: (string) $batch->getKey(),
                    externalReference: $batch->reference,
                    metadata: [
                        'challenge_reference' => $challenge?->reference,
                    ],
                ),
                idempotencyKey: 'x-change:batch-recovery:'.$eventId,
                payload: $payload,
                metadata: [
                    'schema' => 'x-change.batch-recovery-journal.v1',
                    'domain' => 'issuance_batch_recovery',
                    'source' => 'public_batch_workspace',
                ],
            ));
        } catch (Throwable $exception) {
            Log::warning('x-change.batch_recovery_journal_failed', [
                'event_type' => $eventType,
                'batch_reference' => $batch->reference,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostics(Request $request): array
    {
        $userAgent = (string) $request->userAgent();
        $appKey = (string) config('app.key', 'x-change');

        return [
            'environment' => (string) config('app.env', 'unknown'),
            'application' => (string) config('app.name', 'Laravel'),
            'request_id' => (string) ($request->header('X-Request-Id') ?: $request->header('X-Correlation-Id') ?: ''),
            'user_agent_family' => $this->userAgentFamily($userAgent),
            'os_family' => $this->osFamily($userAgent),
            'user_agent_hash' => hash_hmac('sha256', $userAgent, $appKey),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), $appKey),
            'telemetry' => $this->telemetry($request->input('telemetry')),
        ];
    }

    private function userAgentFamily(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    private function osFamily(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Other',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function telemetry(mixed $telemetry): array
    {
        if (! is_array($telemetry)) {
            return [];
        }

        $allowed = [
            'device_memory_gb',
            'hardware_concurrency',
            'viewport_width',
            'viewport_height',
            'connection_type',
            'page_load_ms',
        ];

        return collect($telemetry)
            ->only($allowed)
            ->map(fn (mixed $value): mixed => is_scalar($value) ? $value : null)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->all();
    }
}
