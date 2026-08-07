<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use InvalidArgumentException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use RuntimeException;

final class ClaimExperiencePreviewService
{
    public function __construct(
        private readonly ClaimPreviewScenarioFactory $scenarios,
        private readonly ClaimPreviewVoucherPayloadFactory $payloads,
        private readonly ClaimPreviewArtifactCache $cache,
        private readonly ClaimWalkthroughArtifactStore $artifacts,
        private readonly ClaimWalkthroughStoryboardBuilder $storyboards,
        private readonly ClaimWalkthroughRecorder $recorder,
        private readonly ClaimPreviewVoucherIssuer $previewVouchersIssuer,
        private readonly ClaimPreviewJourneyManifestFactory $journeys,
        private readonly ClaimPreviewVoucherDisposer $previewVouchers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function renderFromInstructions(
        VoucherInstructionsData $instructions,
        ClaimExperiencePreviewOptions $options = new ClaimExperiencePreviewOptions,
    ): array {
        if ($options->issuer === null) {
            throw new InvalidArgumentException('Claim experience preview requires an authenticated issuer.');
        }

        $scenario = $this->scenarios->fromInstructions($instructions);
        $captureAvailable = ! $options->dryRun && $this->recorder->isAvailable();
        $context = $this->cache->context($scenario, [
            'profile' => $options->profile,
            'dry_run' => $options->dryRun,
            'render_mode' => $captureAvailable ? 'browser_capture' : 'storyboard',
            'submit_claim' => false,
            'mobile' => $options->mobile,
            'bank_code' => $options->bankCode,
            'account_number' => $options->accountNumber,
        ]);

        if (! $options->refresh) {
            $cached = $this->cache->find(
                $context['fingerprint'],
                $options->issuer,
            );

            if ($cached !== null) {
                return $this->normalizeReport(
                    $this->cache->reportFor($cached),
                    true,
                    $scenario,
                );
            }
        }

        $run = $this->artifacts->prepareAt(
            'claim-preview-'.substr($context['fingerprint'], 0, 12),
            $context['root'],
        );
        $baseUrl = rtrim($options->baseUrl ?? (string) config('app.url', 'http://localhost'), '/');
        $payCode = null;
        $previewVoucherId = null;

        try {
            if ($captureAvailable) {
                $issued = $this->previewVouchersIssuer->issue(
                    $options->issuer,
                    $this->payloads->make($instructions, $options->issuer),
                );
                $previewVoucherId = $issued['voucher_id'] ?? null;
                $payCode = (string) $issued['code'];
            }

            $report = $captureAvailable
                ? $this->recordOrBuildStoryboard(
                    scenario: $scenario,
                    run: $run,
                    baseUrl: $baseUrl,
                    payCode: $payCode,
                    options: $options,
                )
                : $this->buildStoryboard(
                    scenario: $scenario,
                    run: $run,
                    baseUrl: $baseUrl,
                    reason: $options->dryRun
                        ? 'dry_run_requested'
                        : 'browser_capture_unavailable',
                );

            $journey = $this->journeys->fromReport($report, $scenario);
            $artifact = $this->cache->rememberRendered(
                scenario: $scenario,
                fingerprint: $context['fingerprint'],
                relativePath: $context['relative_path'],
                profile: $options->profile,
                payload: $context['payload'],
                report: $report,
                journey: $journey,
                owner: $options->issuer,
            );

            data_set($report, 'cache.hit', false);
            data_set($report, 'cache.artifact_reference', $artifact->reference);
            data_set($report, 'cache.artifact_fingerprint', $context['fingerprint']);

            data_set($report, 'preview.journey', $journey);

            return $this->normalizeReport($report, false, $scenario);
        } finally {
            $this->previewVouchers->dispose($previewVoucherId);
        }
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array{run_id: string, root: string, screenshots: string, storyboard_frames: string}  $run
     * @return array<string, mixed>
     */
    private function recordOrBuildStoryboard(
        array $scenario,
        array $run,
        string $baseUrl,
        ?string $payCode,
        ClaimExperiencePreviewOptions $options,
    ): array {
        try {
            return $this->recorder->record(
                scenario: $scenario,
                baseUrl: $baseUrl,
                artifactDirectory: $run['root'],
                headed: $options->headed,
                slowMotion: $options->slowMotion,
                options: [
                    'pay_code' => $payCode,
                    'mobile' => $options->mobile,
                    'bank_code' => $options->bankCode,
                    'account_number' => $options->accountNumber,
                    'submit_claim' => false,
                ],
            );
        } catch (RuntimeException $exception) {
            logger()->warning('Claim preview browser capture unavailable; using storyboard.', [
                'exception' => $exception::class,
            ]);

            return $this->buildStoryboard(
                scenario: $scenario,
                run: $run,
                baseUrl: $baseUrl,
                reason: 'browser_capture_failed',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array{run_id: string, root: string, screenshots: string, storyboard_frames: string}  $run
     * @return array<string, mixed>
     */
    private function buildStoryboard(
        array $scenario,
        array $run,
        string $baseUrl,
        string $reason,
    ): array {
        return $this->storyboards->build($scenario, $run, [[
            'sequence' => 1,
            'event' => 'storyboard',
            'status' => 'passed',
            'message' => 'Claim journey storyboard created without launching a browser.',
        ]], [
            'dry_run' => true,
            'capture_mode' => 'storyboard',
            'capture_reason' => $reason,
            'base_url' => $baseUrl,
            'money_movement' => false,
            'source' => 'ClaimExperiencePreviewService',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function normalizeReport(array $report, bool $cacheHit, ?array $scenario = null): array
    {
        $scenario ??= is_array($report['scenario_manifest'] ?? null)
            ? $report['scenario_manifest']
            : [];

        return [
            'schema' => 'x-change.claim-experience-preview.result.v1',
            'status' => 'ready',
            'cache_hit' => $cacheHit,
            'reference' => data_get($report, 'cache.artifact_reference'),
            'fingerprint' => data_get($report, 'cache.artifact_fingerprint'),
            'scenario' => $report['scenario'] ?? null,
            'dry_run' => (bool) ($report['dry_run'] ?? false),
            'artifacts' => $report['artifacts'] ?? [],
            'journey' => data_get($report, 'preview.journey')
                ?: $this->journeys->fromReport($report, $scenario),
            'report' => $report,
        ];
    }
}
