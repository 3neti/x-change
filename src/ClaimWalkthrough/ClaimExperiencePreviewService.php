<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use InvalidArgumentException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;

final class ClaimExperiencePreviewService
{
    public function __construct(
        private readonly ClaimPreviewScenarioFactory $scenarios,
        private readonly ClaimPreviewVoucherPayloadFactory $payloads,
        private readonly ClaimPreviewArtifactCache $cache,
        private readonly ClaimWalkthroughArtifactStore $artifacts,
        private readonly ClaimWalkthroughStoryboardBuilder $storyboards,
        private readonly ClaimWalkthroughRecorder $recorder,
        private readonly PayCodeIssuanceContract $issuance,
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
        $context = $this->cache->context($scenario, [
            'profile' => $options->profile,
            'dry_run' => $options->dryRun,
            'submit_claim' => $options->submitClaim,
            'mobile' => $options->mobile,
            'bank_code' => $options->bankCode,
            'account_number' => $options->accountNumber,
        ]);

        if (! $options->refresh) {
            $cached = $this->cache->find($context['fingerprint']);

            if ($cached !== null) {
                return $this->normalizeReport($this->cache->reportFor($cached), true);
            }
        }

        $run = $this->artifacts->prepareAt(
            'claim-preview-'.substr($context['fingerprint'], 0, 12),
            $context['root'],
        );
        $baseUrl = rtrim($options->baseUrl ?? (string) config('app.url', 'http://localhost'), '/');
        $payCode = null;

        if (! $options->dryRun) {
            $issued = $this->issuance->issue(
                $options->issuer,
                $this->payloads->make($instructions, $options->issuer),
            );
            $payCode = (string) $issued['code'];
        }

        $report = $options->dryRun
            ? $this->storyboards->build($scenario, $run, [[
                'sequence' => 1,
                'event' => 'dry-run',
                'status' => 'passed',
                'message' => 'Storyboard scaffold created without launching a browser.',
            ]], [
                'dry_run' => true,
                'base_url' => $baseUrl,
                'money_movement' => false,
                'source' => 'ClaimExperiencePreviewService',
            ])
            : $this->recorder->record(
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
                    'submit_claim' => $options->submitClaim,
                ],
            );

        $artifact = $this->cache->rememberRendered(
            scenario: $scenario,
            fingerprint: $context['fingerprint'],
            relativePath: $context['relative_path'],
            profile: $options->profile,
            payload: $context['payload'],
            report: $report,
        );

        data_set($report, 'cache.hit', false);
        data_set($report, 'cache.artifact_reference', $artifact->reference);
        data_set($report, 'cache.artifact_fingerprint', $context['fingerprint']);

        return $this->normalizeReport($report, false);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function normalizeReport(array $report, bool $cacheHit): array
    {
        return [
            'schema' => 'x-change.claim-experience-preview.result.v1',
            'status' => 'ready',
            'cache_hit' => $cacheHit,
            'reference' => data_get($report, 'cache.artifact_reference'),
            'fingerprint' => data_get($report, 'cache.artifact_fingerprint'),
            'scenario' => $report['scenario'] ?? null,
            'dry_run' => (bool) ($report['dry_run'] ?? false),
            'artifacts' => $report['artifacts'] ?? [],
            'report' => $report,
        ];
    }
}
