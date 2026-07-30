<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactCache;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughArtifactStore;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughQaMatrix;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughRecorder;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughScenarioRepository;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughStoryboardBuilder;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use Throwable;

final class ClaimWalkthroughCommand extends Command
{
    protected $signature = 'xchange:claim-walkthrough
        {scenario=claim_basic_no_rider : Storyboard scenario key}
        {--list : List available walkthrough scenarios}
        {--qa-matrix : Output the no-money public claim storyboard QA matrix}
        {--qa-batch : Render all available no-money public claim storyboard QA matrix entries and create an index}
        {--qa-review= : Read a QA batch JSON manifest or Markdown worksheet and summarize review statuses}
        {--qa-diff-from= : Previous QA batch JSON manifest or batch folder for storyboard diffing}
        {--qa-diff-to= : Current QA batch JSON manifest or batch folder for storyboard diffing}
        {--qa-diff-output= : Override the QA diff Markdown output path}
        {--qa-acceptance= : Read a QA batch JSON manifest or Markdown worksheet and generate an acceptance report}
        {--qa-acceptance-output= : Override the acceptance report Markdown output path}
        {--qa-review-output= : Override the review summary JSON output path}
        {--dry-run : Create storyboard artifacts without launching a browser}
        {--code= : Use an existing Pay Code for browser capture}
        {--create-fixture : Generate a disposable walkthrough Pay Code before browser capture}
        {--issuer=1 : Issuer id used when generating a walkthrough fixture}
        {--mobile=09173011987 : Mobile number entered in the claim form}
        {--bank-code=GXCHPHM2XXX : Bank or wallet code entered in the claim form}
        {--account-number=09173011987 : Bank or wallet account number entered in the claim form}
        {--submit-claim : Click the final Confirm & Claim button; may trigger the configured payout provider}
        {--preview-cache : Reuse or store deterministic no-money claim preview artifacts}
        {--refresh-preview : Ignore an existing preview artifact cache entry and render again}
        {--profile=issuer : Artifact profile: issuer, developer, qa, or support}
        {--headed : Show browser while recording}
        {--slow-mo=100 : Delay browser actions in milliseconds}
        {--base-url= : Local app URL; defaults to app.url}
        {--run-id= : Override artifact run id}
        {--json : Output JSON}';

    protected $description = 'Record or scaffold a browser storyboard for the x-change claim experience.';

    public function handle(
        ClaimWalkthroughScenarioRepository $scenarios,
        ClaimWalkthroughArtifactStore $artifacts,
        ClaimWalkthroughStoryboardBuilder $storyboards,
        ClaimWalkthroughRecorder $recorder,
        GeneratePayCode $payCodes,
        PayCodeIssuanceContract $issuance,
        ClaimPreviewArtifactCache $previewArtifacts,
        ClaimWalkthroughQaMatrix $qaMatrix,
    ): int {
        if ($this->option('list')) {
            return $this->listScenarios($scenarios);
        }

        if ($this->option('qa-matrix')) {
            return $this->renderQaMatrix($qaMatrix->report($scenarios));
        }

        if ($this->option('qa-batch')) {
            return $this->renderQaBatch($qaMatrix->report($scenarios), $scenarios, $artifacts, $storyboards, $previewArtifacts);
        }

        if (is_string($this->option('qa-review')) && trim((string) $this->option('qa-review')) !== '') {
            return $this->renderQaReviewSummary(
                $artifacts,
                trim((string) $this->option('qa-review')),
                is_string($this->option('qa-review-output')) && trim((string) $this->option('qa-review-output')) !== ''
                    ? trim((string) $this->option('qa-review-output'))
                    : null,
            );
        }

        if (
            is_string($this->option('qa-diff-from'))
            && trim((string) $this->option('qa-diff-from')) !== ''
            && is_string($this->option('qa-diff-to'))
            && trim((string) $this->option('qa-diff-to')) !== ''
        ) {
            return $this->renderQaDiff(
                $artifacts,
                trim((string) $this->option('qa-diff-from')),
                trim((string) $this->option('qa-diff-to')),
                is_string($this->option('qa-diff-output')) && trim((string) $this->option('qa-diff-output')) !== ''
                    ? trim((string) $this->option('qa-diff-output'))
                    : null,
            );
        }

        if (is_string($this->option('qa-acceptance')) && trim((string) $this->option('qa-acceptance')) !== '') {
            return $this->renderQaAcceptanceReport(
                $artifacts,
                trim((string) $this->option('qa-acceptance')),
                is_string($this->option('qa-acceptance-output')) && trim((string) $this->option('qa-acceptance-output')) !== ''
                    ? trim((string) $this->option('qa-acceptance-output'))
                    : null,
            );
        }

        $scenarioKey = (string) $this->argument('scenario');

        if (! $scenarios->exists($scenarioKey)) {
            $this->error("Unknown claim walkthrough scenario [{$scenarioKey}].");

            return self::FAILURE;
        }

        $baseUrl = $this->resolveBaseUrl();

        if (! $this->isLocalBaseUrl($baseUrl)) {
            $this->error('Claim walkthroughs only run against local URLs, such as http://x-change-sandbox.test.');

            return self::FAILURE;
        }

        $slowMotion = $this->resolveSlowMotion();

        if ($slowMotion === null) {
            return self::FAILURE;
        }

        $scenario = $scenarios->get($scenarioKey);
        $profile = $this->resolveProfile();

        if ($profile === null) {
            return self::FAILURE;
        }

        $previewCache = (bool) $this->option('preview-cache');
        $previewContext = null;

        if ($previewCache) {
            if ((bool) data_get($scenario, 'fixture.money_movement', false)) {
                $this->error('Preview artifact caching is only available for no-money claim preview scenarios.');

                return self::FAILURE;
            }

            $previewContext = $previewArtifacts->context($scenario, [
                'profile' => $profile,
                'dry_run' => (bool) $this->option('dry-run'),
                'submit_claim' => (bool) $this->option('submit-claim'),
                'mobile' => (string) $this->option('mobile'),
                'bank_code' => (string) $this->option('bank-code'),
                'account_number' => (string) $this->option('account-number'),
            ]);

            if (! (bool) $this->option('refresh-preview')) {
                $cachedArtifact = $previewArtifacts->find($previewContext['fingerprint']);

                if ($cachedArtifact !== null) {
                    $payload = $previewArtifacts->reportFor($cachedArtifact);

                    if ($this->option('json')) {
                        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                        return self::SUCCESS;
                    }

                    $this->info('Claim preview artifact cache hit.');
                    $this->line('Scenario: '.($payload['scenario'] ?? $scenarioKey));
                    $this->renderArtifactViewOptions($payload);

                    return self::SUCCESS;
                }
            }
        }

        $run = $previewContext !== null
            ? $artifacts->prepareAt(
                'claim-preview-'.substr($previewContext['fingerprint'], 0, 12),
                $previewContext['root'],
            )
            : $artifacts->prepare($scenarioKey, $this->option('run-id') ? (string) $this->option('run-id') : null);
        $payCode = $this->resolvePayCode($scenario, $payCodes, $issuance);

        try {
            $payload = $this->option('dry-run')
                ? $this->buildDryRun($scenario, $run, $baseUrl, $storyboards)
                : $recorder->record(
                    scenario: $scenario,
                    baseUrl: $baseUrl,
                    artifactDirectory: $run['root'],
                    headed: (bool) $this->option('headed'),
                    slowMotion: $slowMotion,
                    options: [
                        'pay_code' => $payCode,
                        'mobile' => (string) $this->option('mobile'),
                        'bank_code' => (string) $this->option('bank-code'),
                        'account_number' => (string) $this->option('account-number'),
                        'submit_claim' => (bool) $this->option('submit-claim'),
                    ],
                );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($previewContext !== null) {
            $previewArtifacts->rememberRendered(
                scenario: $scenario,
                fingerprint: $previewContext['fingerprint'],
                relativePath: $previewContext['relative_path'],
                profile: $profile,
                payload: $previewContext['payload'],
                report: $payload,
            );

            data_set($payload, 'cache.hit', false);
            data_set($payload, 'cache.artifact_fingerprint', $previewContext['fingerprint']);
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Claim walkthrough artifacts created.');
        $this->line('Scenario: '.($payload['scenario'] ?? $scenarioKey));
        $this->line('Run: '.($payload['run_id'] ?? $run['run_id']));
        $this->renderArtifactViewOptions($payload);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function resolvePayCode(
        array $scenario,
        GeneratePayCode $payCodes,
        PayCodeIssuanceContract $issuance
    ): ?string {
        $code = $this->option('code');

        if (is_string($code) && trim($code) !== '') {
            return strtoupper(trim($code));
        }

        if (! (bool) $this->option('create-fixture')) {
            return null;
        }

        if (! (bool) data_get($scenario, 'fixture.money_movement', false)) {
            $issued = $issuance->issue(
                $this->resolveFixtureIssuer(),
                $this->fixturePayCodePayload($scenario),
            );

            return (string) $issued['code'];
        }

        $result = $payCodes->handle($this->fixturePayCodePayload($scenario));

        return $result->code;
    }

    private function resolveFixtureIssuer(): Authenticatable
    {
        $issuerId = (string) $this->option('issuer');
        $issuerModel = config(
            'x-change.onboarding.issuer_model',
            config('auth.providers.users.model')
        );

        if (! is_string($issuerModel) || ! class_exists($issuerModel)) {
            throw new PayCodeIssuerNotResolved('Unable to resolve Pay Code issuer model for walkthrough fixture.');
        }

        $issuer = $issuerModel::query()->find($issuerId);

        if (! $issuer instanceof Authenticatable) {
            throw new PayCodeIssuerNotResolved('Unable to resolve Pay Code issuer for walkthrough fixture.');
        }

        return $issuer;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function fixturePayCodePayload(array $scenario): array
    {
        $issuer = (string) $this->option('issuer');
        $fixture = $scenario['fixture'] ?? [];
        $amount = (float) ($fixture['amount'] ?? 15.00);

        return [
            'cash' => [
                'amount' => $amount,
                'currency' => 'PHP',
                'settlement_rail' => 'INSTAPAY',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
                'fee_strategy' => 'absorb',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => data_get($fixture, 'rider.message'),
                'url' => data_get($fixture, 'rider.url'),
                'redirect_timeout' => data_get($fixture, 'rider.redirect_timeout'),
                'splash' => data_get($fixture, 'rider.splash'),
                'splash_timeout' => data_get($fixture, 'rider.splash_timeout'),
                'splash_meta' => [
                    'sanitized' => true,
                    'html_profile' => 'rider_splash',
                ],
                'og_source' => data_get($fixture, 'rider.og_source'),
            ],
            'count' => 1,
            'prefix' => 'QA',
            'mask' => '****',
            'ttl' => null,
            'metadata' => [
                'issuer_id' => $issuer,
                'slices' => data_get($fixture, 'slices'),
                'created_at' => now()->toIso8601String(),
                'issued_at' => now()->toIso8601String(),
                'walkthrough' => [
                    'scenario' => $scenario['key'] ?? null,
                    'purpose' => 'claim-ui-browser-storyboard',
                ],
            ],
        ];
    }

    private function listScenarios(ClaimWalkthroughScenarioRepository $scenarios): int
    {
        foreach ($scenarios->all() as $scenario) {
            $this->line(sprintf('%s - %s', $scenario['key'], $scenario['label']));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderQaMatrix(array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Claim storyboard QA matrix');
        $this->line('Surface: '.data_get($payload, 'boundary.surface'));
        $this->line('Money movement: disabled');
        $this->line('Submit claim: disabled');
        $this->line('');

        foreach ((array) ($payload['entries'] ?? []) as $entry) {
            $this->line(sprintf(
                '[%s] %s %s',
                data_get($entry, 'priority'),
                data_get($entry, 'status'),
                data_get($entry, 'scenario') ?: data_get($entry, 'lane'),
            ));

            $command = data_get($entry, 'command');

            if (is_string($command) && $command !== '') {
                $this->line('  '.$command);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $matrix
     */
    private function renderQaBatch(
        array $matrix,
        ClaimWalkthroughScenarioRepository $scenarios,
        ClaimWalkthroughArtifactStore $artifacts,
        ClaimWalkthroughStoryboardBuilder $storyboards,
        ClaimPreviewArtifactCache $previewArtifacts,
    ): int {
        $baseUrl = $this->resolveBaseUrl();

        if (! $this->isLocalBaseUrl($baseUrl)) {
            $this->error('Claim walkthroughs only run against local URLs, such as http://x-change-sandbox.test.');

            return self::FAILURE;
        }

        $profile = $this->resolveProfile();

        if ($profile === null) {
            return self::FAILURE;
        }

        $runId = (string) ($this->option('run-id') ?: 'claim-qa-batch-'.now()->format('Ymd-His'));
        $batchRoot = storage_path('app/x-change/claim-preview-batches/'.$runId);
        $batch = $artifacts->prepareAt($runId, $batchRoot);
        $entries = [];

        foreach ((array) ($matrix['entries'] ?? []) as $entry) {
            if (! $this->isRenderableQaBatchEntry($entry)) {
                continue;
            }

            $scenarioKey = (string) data_get($entry, 'scenario');

            if (! $scenarios->exists($scenarioKey)) {
                continue;
            }

            $scenario = $scenarios->get($scenarioKey);
            $previewContext = $previewArtifacts->context($scenario, [
                'profile' => $profile,
                'dry_run' => true,
                'submit_claim' => false,
                'mobile' => (string) $this->option('mobile'),
                'bank_code' => (string) $this->option('bank-code'),
                'account_number' => (string) $this->option('account-number'),
            ]);
            $cacheHit = false;

            if (! (bool) $this->option('refresh-preview')) {
                $cachedArtifact = $previewArtifacts->find($previewContext['fingerprint']);

                if ($cachedArtifact !== null) {
                    $report = $previewArtifacts->reportFor($cachedArtifact);
                    $cacheHit = true;
                }
            }

            if (! isset($report)) {
                $run = $artifacts->prepareAt(
                    'claim-preview-'.substr($previewContext['fingerprint'], 0, 12),
                    $previewContext['root'],
                );
                $report = $this->buildDryRun($scenario, $run, $baseUrl, $storyboards);

                $previewArtifacts->rememberRendered(
                    scenario: $scenario,
                    fingerprint: $previewContext['fingerprint'],
                    relativePath: $previewContext['relative_path'],
                    profile: $profile,
                    payload: $previewContext['payload'],
                    report: $report,
                );
            }

            $entries[] = [
                'priority' => data_get($entry, 'priority'),
                'scenario' => $scenarioKey,
                'label' => data_get($entry, 'label'),
                'review' => $this->qaBatchEntryReviewState(),
                'cache_hit' => $cacheHit,
                'artifact_fingerprint' => $previewContext['fingerprint'],
                'storyboard_html' => data_get($report, 'artifacts.storyboard_html'),
                'storyboard_pdf' => data_get($report, 'artifacts.storyboard_pdf'),
                'artifact_root' => data_get($report, 'artifacts.root'),
            ];

            unset($report);
        }

        $payload = [
            'schema' => 'x-change.claim-walkthrough.qa-batch.v1',
            'generated_at' => now()->toIso8601String(),
            'run_id' => $runId,
            'dry_run' => true,
            'profile' => $profile,
            'boundary' => $matrix['boundary'] ?? [],
            'review_checklist' => $this->qaBatchReviewChecklist(),
            'entry_count' => count($entries),
            'entries' => $entries,
            'artifacts' => [
                'root' => $batch['root'],
                'index_json' => $batch['root'].'/claim-walkthrough-qa-batch.json',
                'index_html' => $batch['root'].'/claim-walkthrough-qa-batch.html',
                'review_markdown' => $batch['root'].'/claim-walkthrough-qa-review.md',
            ],
        ];
        $payload['artifacts']['view_options'] = $this->qaBatchViewOptions($payload['artifacts']);

        $artifacts->writeJson($payload['artifacts']['index_json'], $payload);
        $artifacts->writeHtml($payload['artifacts']['index_html'], $this->renderQaBatchHtml($payload));
        $artifacts->writeText($payload['artifacts']['review_markdown'], $this->renderQaBatchReviewMarkdown($payload));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Claim storyboard QA batch created.');
        $this->line('Entries: '.count($entries));
        $this->renderQaBatchViewOptions($payload);

        return self::SUCCESS;
    }

    private function renderQaReviewSummary(
        ClaimWalkthroughArtifactStore $artifacts,
        string $source,
        ?string $outputPath,
    ): int {
        $path = $this->resolveQaReviewSourcePath($source);

        if (! $artifacts->exists($path)) {
            $this->error("QA review source [{$path}] does not exist.");

            return self::FAILURE;
        }

        $payload = $this->qaReviewSummaryPayload($artifacts, $path);
        $summaryPath = $outputPath ?: $this->defaultQaReviewSummaryPath($path);
        $payload['artifacts'] = [
            'review_summary_json' => $summaryPath,
        ];

        $artifacts->writeJson($summaryPath, $payload);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Claim storyboard QA review summary');
        $this->line('Source: '.$payload['source']);
        $this->line('Summary JSON: '.$summaryPath);
        $this->line('Entries: '.$payload['entry_count']);
        $this->line('Pass: '.data_get($payload, 'counts.pass', 0));
        $this->line('Needs fix: '.data_get($payload, 'counts.needs_fix', 0));
        $this->line('Blocker: '.data_get($payload, 'counts.blocker', 0));
        $this->line('Unreviewed: '.data_get($payload, 'counts.unreviewed', 0));

        return self::SUCCESS;
    }

    private function renderQaDiff(
        ClaimWalkthroughArtifactStore $artifacts,
        string $from,
        string $to,
        ?string $outputPath,
    ): int {
        $fromPath = $this->resolveQaBatchManifestPath($from);
        $toPath = $this->resolveQaBatchManifestPath($to);

        if (! $artifacts->exists($fromPath)) {
            $this->error("Previous QA batch manifest [{$fromPath}] does not exist.");

            return self::FAILURE;
        }

        if (! $artifacts->exists($toPath)) {
            $this->error("Current QA batch manifest [{$toPath}] does not exist.");

            return self::FAILURE;
        }

        $payload = $this->qaDiffPayload(
            $this->readQaBatchManifest($artifacts, $fromPath),
            $this->readQaBatchManifest($artifacts, $toPath),
            $fromPath,
            $toPath,
        );
        $diffPath = $outputPath ?: $this->defaultQaDiffReportPath($toPath);
        $payload['artifacts'] = [
            'diff_markdown' => $diffPath,
        ];

        $artifacts->writeText($diffPath, $this->renderQaDiffMarkdown($payload));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Claim storyboard QA diff');
        $this->line('From: '.$payload['from']['run_id']);
        $this->line('To: '.$payload['to']['run_id']);
        $this->line('Added: '.data_get($payload, 'counts.added', 0));
        $this->line('Removed: '.data_get($payload, 'counts.removed', 0));
        $this->line('Changed: '.data_get($payload, 'counts.changed', 0));
        $this->line('Unchanged: '.data_get($payload, 'counts.unchanged', 0));
        $this->line('Diff report: '.$diffPath);

        foreach ((array) data_get($payload, 'entries', []) as $entry) {
            if (data_get($entry, 'status') !== 'unchanged') {
                $this->line(sprintf(
                    '[%s] %s',
                    data_get($entry, 'status'),
                    data_get($entry, 'scenario'),
                ));
            }
        }

        return self::SUCCESS;
    }

    private function renderQaAcceptanceReport(
        ClaimWalkthroughArtifactStore $artifacts,
        string $source,
        ?string $outputPath,
    ): int {
        $path = $this->resolveQaReviewSourcePath($source);

        if (! $artifacts->exists($path)) {
            $this->error("QA acceptance source [{$path}] does not exist.");

            return self::FAILURE;
        }

        $summary = $this->qaReviewSummaryPayload($artifacts, $path);
        $reportPath = $outputPath ?: $this->defaultQaAcceptanceReportPath($path);
        $payload = [
            'schema' => 'x-change.claim-walkthrough.qa-acceptance.v1',
            'generated_at' => now()->toIso8601String(),
            'source' => $path,
            'report_markdown' => $reportPath,
            'review' => $summary,
        ];

        $artifacts->writeText($reportPath, $this->renderQaAcceptanceMarkdown($payload));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Claim UX acceptance report created.');
        $this->line('Report: '.$reportPath);
        $this->line('Pass: '.data_get($summary, 'counts.pass', 0));
        $this->line('Needs fix: '.data_get($summary, 'counts.needs_fix', 0));
        $this->line('Blocker: '.data_get($summary, 'counts.blocker', 0));
        $this->line('Unreviewed: '.data_get($summary, 'counts.unreviewed', 0));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $artifacts
     * @return array<string, array<string, mixed>>
     */
    private function qaBatchViewOptions(array $artifacts): array
    {
        return [
            'default' => [
                'label' => 'Default HTML index',
                'kind' => 'html',
                'path' => $artifacts['index_html'],
                'url' => 'file://'.$artifacts['index_html'],
                'open_command' => 'open '.escapeshellarg((string) $artifacts['index_html']),
            ],
            'json' => [
                'label' => 'JSON manifest',
                'kind' => 'json',
                'path' => $artifacts['index_json'],
                'url' => 'file://'.$artifacts['index_json'],
                'open_command' => 'open '.escapeshellarg((string) $artifacts['index_json']),
            ],
            'review' => [
                'label' => 'Markdown review worksheet',
                'kind' => 'markdown',
                'path' => $artifacts['review_markdown'],
                'url' => 'file://'.$artifacts['review_markdown'],
                'open_command' => 'open '.escapeshellarg((string) $artifacts['review_markdown']),
            ],
            'folder' => [
                'label' => 'Artifact folder',
                'kind' => 'folder',
                'path' => $artifacts['root'],
                'url' => 'file://'.$artifacts['root'],
                'open_command' => 'open '.escapeshellarg((string) $artifacts['root']),
            ],
            'current_app' => [
                'label' => 'Current app paths',
                'kind' => 'paths',
                'root' => $artifacts['root'],
                'html' => $artifacts['index_html'],
                'json' => $artifacts['index_json'],
                'review_markdown' => $artifacts['review_markdown'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderQaBatchViewOptions(array $payload): void
    {
        $viewOptions = data_get($payload, 'artifacts.view_options', []);

        $this->line('View options:');
        $this->line('  Default HTML index: '.data_get($viewOptions, 'default.path'));
        $this->line('  JSON manifest: '.data_get($viewOptions, 'json.path'));
        $this->line('  Review worksheet: '.data_get($viewOptions, 'review.path'));
        $this->line('  Artifact folder: '.data_get($viewOptions, 'folder.path'));
        $this->line('  Open HTML index: '.data_get($viewOptions, 'default.open_command'));
        $this->line('  Open review worksheet: '.data_get($viewOptions, 'review.open_command'));
        $this->line('  Open folder: '.data_get($viewOptions, 'folder.open_command'));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isRenderableQaBatchEntry(array $entry): bool
    {
        return data_get($entry, 'status') === 'available'
            && is_string(data_get($entry, 'scenario'))
            && data_get($entry, 'money_movement') === false
            && data_get($entry, 'submit_claim') === false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderQaBatchHtml(array $payload): string
    {
        $checklist = $this->renderQaBatchChecklist((array) data_get($payload, 'review_checklist', []));
        $cards = collect((array) ($payload['entries'] ?? []))
            ->map(function (array $entry) use ($checklist): string {
                $priority = e((string) data_get($entry, 'priority'));
                $label = e((string) data_get($entry, 'label'));
                $scenario = e((string) data_get($entry, 'scenario'));
                $html = e((string) data_get($entry, 'storyboard_html'));
                $pdf = e((string) data_get($entry, 'storyboard_pdf'));
                $cache = data_get($entry, 'cache_hit') ? 'cache hit' : 'rendered';
                $reviewState = e((string) data_get($entry, 'review.status', 'unreviewed'));
                $reviewOptions = $this->renderQaBatchReviewOptions((array) data_get($entry, 'review.allowed_statuses', []));

                return <<<HTML
                    <section class="card">
                        <div class="meta">{$priority} · {$cache}</div>
                        <h2>{$label}</h2>
                        <p><code>{$scenario}</code></p>
                        <div class="links">
                            <a href="file://{$html}">HTML storyboard</a>
                            <a href="file://{$pdf}">PDF storyboard</a>
                        </div>
                        <div class="status">
                            <h3>Reviewer status</h3>
                            <p>Current: <strong>{$reviewState}</strong></p>
                            <div class="choices">
                                {$reviewOptions}
                            </div>
                            <div class="notes">Notes:</div>
                        </div>
                        <div class="review">
                            <h3>Review checklist</h3>
                            <ul>
                                {$checklist}
                            </ul>
                        </div>
                    </section>
                HTML;
            })
            ->implode(PHP_EOL);
        $runId = e((string) data_get($payload, 'run_id'));
        $count = e((string) data_get($payload, 'entry_count'));
        $reviewMarkdown = e((string) data_get($payload, 'artifacts.review_markdown'));

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Claim Storyboard QA Batch</title>
                <style>
                    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
                    main { max-width: 1080px; margin: 0 auto; padding: 40px 24px; }
                    header { margin-bottom: 28px; }
                    h1 { margin: 0 0 8px; font-size: 32px; line-height: 1.1; }
                    h2 { margin: 6px 0 8px; font-size: 18px; }
                    h3 { margin: 18px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #334155; }
                    p { color: #475569; }
                    code { background: #eef2ff; border-radius: 4px; padding: 2px 5px; }
                    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
                    .card { border: 1px solid #d9e2ec; background: white; border-radius: 8px; padding: 18px; }
                    .meta { color: #b91c1c; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
                    .links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
                    .status { border-top: 1px solid #e2e8f0; margin-top: 16px; padding-top: 2px; }
                    .status p { margin: 0 0 10px; font-size: 13px; }
                    .choices { display: flex; flex-wrap: wrap; gap: 8px; }
                    .choice { border: 1px solid #cbd5e1; border-radius: 999px; color: #334155; font-size: 12px; font-weight: 700; padding: 5px 9px; }
                    .choice::before { content: "[ ] "; color: #64748b; }
                    .notes { border: 1px dashed #cbd5e1; border-radius: 6px; color: #64748b; font-size: 13px; margin-top: 10px; min-height: 42px; padding: 8px; }
                    .review { border-top: 1px solid #e2e8f0; margin-top: 16px; padding-top: 2px; }
                    .review ul { display: grid; gap: 7px; margin: 0; padding: 0; list-style: none; }
                    .review li { position: relative; padding-left: 22px; color: #334155; font-size: 13px; line-height: 1.45; }
                    .review li::before { content: "☐"; position: absolute; left: 0; top: 0; color: #64748b; font-weight: 700; }
                    a { color: #0f766e; font-weight: 700; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                </style>
            </head>
            <body>
                <main>
                    <header>
                        <h1>Claim Storyboard QA Batch</h1>
                        <p>Run {$runId} · {$count} no-money storyboard previews</p>
                        <p><a href="file://{$reviewMarkdown}">Open Markdown review worksheet</a></p>
                    </header>
                    <div class="grid">
                        {$cards}
                    </div>
                </main>
            </body>
            </html>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderQaBatchReviewMarkdown(array $payload): string
    {
        $lines = [
            '# Claim Storyboard QA Review',
            '',
            'Run: '.$this->qaBatchMarkdownText(data_get($payload, 'run_id')),
            'Generated at: '.$this->qaBatchMarkdownText(data_get($payload, 'generated_at')),
            'Profile: '.$this->qaBatchMarkdownText(data_get($payload, 'profile')),
            'Dry run: '.(data_get($payload, 'dry_run') ? 'true' : 'false'),
            '',
            'Safety boundary:',
            '- Cockpit: '.(data_get($payload, 'boundary.cockpit') ? 'true' : 'false'),
            '- Money movement: '.(data_get($payload, 'boundary.money_movement') ? 'true' : 'false'),
            '- Submit claim: '.(data_get($payload, 'boundary.submit_claim') ? 'true' : 'false'),
            '',
            'Allowed reviewer statuses: pass, needs_fix, blocker',
            '',
        ];

        foreach ((array) data_get($payload, 'entries', []) as $entry) {
            $lines[] = '## '.$this->qaBatchMarkdownText(data_get($entry, 'priority')).' - '.$this->qaBatchMarkdownText(data_get($entry, 'label'));
            $lines[] = '';
            $lines[] = 'Scenario: `'.$this->qaBatchMarkdownText(data_get($entry, 'scenario')).'`';
            $lines[] = 'Current review status: `'.$this->qaBatchMarkdownText(data_get($entry, 'review.status', 'unreviewed')).'`';
            $lines[] = 'Storyboard HTML: '.$this->qaBatchMarkdownText(data_get($entry, 'storyboard_html'));
            $lines[] = 'Storyboard PDF: '.$this->qaBatchMarkdownText(data_get($entry, 'storyboard_pdf'));
            $lines[] = 'Artifact fingerprint: `'.$this->qaBatchMarkdownText(data_get($entry, 'artifact_fingerprint')).'`';
            $lines[] = '';
            $lines[] = 'Reviewer status:';
            $lines[] = '- [ ] pass';
            $lines[] = '- [ ] needs_fix';
            $lines[] = '- [ ] blocker';
            $lines[] = '';
            $lines[] = 'Notes:';
            $lines[] = '- ';
            $lines[] = '';
            $lines[] = 'Checklist:';

            foreach ((array) data_get($payload, 'review_checklist', []) as $item) {
                $lines[] = '- [ ] '.$this->qaBatchMarkdownText($item);
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @return list<string>
     */
    private function qaBatchReviewChecklist(): array
    {
        return [
            'Frames represent meaningful visible states without duplicate-looking steps.',
            'No visible overlap, clipped text, or awkward scroll requirement in the captured claim UI.',
            'Primary action and next step are obvious for a first-time redeemer.',
            'Handler permission, retry, and cancel copy is understandable where the lane includes a handler.',
            'Pay Code, amount, and any slice context are readable before submission.',
            'No provider call, Cockpit route, or real money movement appears in this preview.',
        ];
    }

    /**
     * @return array{status: string, allowed_statuses: list<string>, notes: string}
     */
    private function qaBatchEntryReviewState(): array
    {
        return [
            'status' => 'unreviewed',
            'allowed_statuses' => [
                'pass',
                'needs_fix',
                'blocker',
            ],
            'notes' => '',
        ];
    }

    /**
     * @param  list<string>  $items
     */
    private function renderQaBatchChecklist(array $items): string
    {
        return collect($items)
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->implode(PHP_EOL);
    }

    /**
     * @param  list<string>  $statuses
     */
    private function renderQaBatchReviewOptions(array $statuses): string
    {
        return collect($statuses)
            ->map(fn (string $status): string => '<span class="choice">'.e(str_replace('_', ' ', $status)).'</span>')
            ->implode(PHP_EOL);
    }

    private function qaBatchMarkdownText(mixed $value): string
    {
        return str_replace(["\r", "\n"], ' ', trim((string) $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function qaReviewSummaryPayload(ClaimWalkthroughArtifactStore $artifacts, string $path): array
    {
        $entries = str_ends_with($path, '.json')
            ? $this->qaReviewEntriesFromJson($this->readQaBatchManifest($artifacts, $path))
            : $this->qaReviewEntriesFromMarkdown($artifacts->readText($path));

        $counts = [
            'pass' => 0,
            'needs_fix' => 0,
            'blocker' => 0,
            'unreviewed' => 0,
        ];

        foreach ($entries as $entry) {
            $status = (string) data_get($entry, 'review.status', 'unreviewed');

            if (! array_key_exists($status, $counts)) {
                $status = 'unreviewed';
            }

            $counts[$status]++;
        }

        return [
            'schema' => 'x-change.claim-walkthrough.qa-review-summary.v1',
            'generated_at' => now()->toIso8601String(),
            'source' => $path,
            'entry_count' => count($entries),
            'counts' => $counts,
            'accepted' => $counts['blocker'] === 0 && $counts['needs_fix'] === 0 && $counts['unreviewed'] === 0,
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function qaReviewEntriesFromJson(array $manifest): array
    {
        return collect((array) data_get($manifest, 'entries', []))
            ->map(function (array $entry): array {
                return [
                    'scenario' => (string) data_get($entry, 'scenario'),
                    'label' => (string) data_get($entry, 'label'),
                    'priority' => (string) data_get($entry, 'priority'),
                    'review' => [
                        'status' => $this->normalizeQaReviewStatus(data_get($entry, 'review.status')),
                        'notes' => (string) data_get($entry, 'review.notes', ''),
                    ],
                    'artifact_fingerprint' => (string) data_get($entry, 'artifact_fingerprint'),
                    'storyboard_html' => (string) data_get($entry, 'storyboard_html'),
                    'storyboard_pdf' => (string) data_get($entry, 'storyboard_pdf'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function qaReviewEntriesFromMarkdown(string $markdown): array
    {
        $sections = preg_split('/^##\s+/m', $markdown) ?: [];

        return collect(array_slice($sections, 1))
            ->map(function (string $section): ?array {
                $lines = preg_split('/\R/', trim($section)) ?: [];
                $heading = trim((string) ($lines[0] ?? ''));
                $scenario = $this->firstMarkdownMatch('/Scenario:\s+`([^`]+)`/', $section);

                if ($scenario === null) {
                    return null;
                }

                [$priority, $label] = array_pad(explode(' - ', $heading, 2), 2, '');

                return [
                    'scenario' => $scenario,
                    'label' => $label,
                    'priority' => $priority,
                    'review' => [
                        'status' => $this->qaReviewStatusFromMarkdown($section),
                        'notes' => $this->qaReviewNotesFromMarkdown($section),
                    ],
                    'artifact_fingerprint' => $this->firstMarkdownMatch('/Artifact fingerprint:\s+`([^`]+)`/', $section) ?? '',
                    'storyboard_html' => $this->firstMarkdownMatch('/Storyboard HTML:\s+([^\r\n]+)/', $section) ?? '',
                    'storyboard_pdf' => $this->firstMarkdownMatch('/Storyboard PDF:\s+([^\r\n]+)/', $section) ?? '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function qaReviewStatusFromMarkdown(string $section): string
    {
        foreach (['pass', 'needs_fix', 'blocker'] as $status) {
            $label = str_replace('_', '[ _-]', $status);

            if (preg_match('/-\s+\[[xX]\]\s+'.$label.'\b/', $section) === 1) {
                return $status;
            }
        }

        return $this->normalizeQaReviewStatus($this->firstMarkdownMatch('/Current review status:\s+`([^`]+)`/', $section));
    }

    private function qaReviewNotesFromMarkdown(string $section): string
    {
        if (preg_match('/Notes:\s*(.*?)(?:\RChecklist:|\z)/s', $section, $matches) !== 1) {
            return '';
        }

        return trim(preg_replace('/^\s*-\s?/m', '', (string) $matches[1]) ?? '');
    }

    private function normalizeQaReviewStatus(mixed $status): string
    {
        $normalized = str_replace([' ', '-'], '_', strtolower(trim((string) $status)));

        return in_array($normalized, ['pass', 'needs_fix', 'blocker', 'unreviewed'], true)
            ? $normalized
            : 'unreviewed';
    }

    private function firstMarkdownMatch(string $pattern, string $markdown): ?string
    {
        if (preg_match($pattern, $markdown, $matches) !== 1) {
            return null;
        }

        return trim((string) $matches[1]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readQaBatchManifest(ClaimWalkthroughArtifactStore $artifacts, string $path): array
    {
        $payload = $artifacts->readJson($path);

        return data_get($payload, 'schema') === 'x-change.claim-walkthrough.qa-batch.v1'
            ? $payload
            : [];
    }

    private function resolveQaReviewSourcePath(string $source): string
    {
        if (is_dir($source)) {
            $reviewPath = rtrim($source, '/').'/claim-walkthrough-qa-review.md';

            if (is_file($reviewPath)) {
                return $reviewPath;
            }

            return rtrim($source, '/').'/claim-walkthrough-qa-batch.json';
        }

        return $source;
    }

    private function resolveQaBatchManifestPath(string $source): string
    {
        return is_dir($source)
            ? rtrim($source, '/').'/claim-walkthrough-qa-batch.json'
            : $source;
    }

    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return array<string, mixed>
     */
    private function qaDiffPayload(array $from, array $to, string $fromPath, string $toPath): array
    {
        $fromEntries = collect((array) data_get($from, 'entries', []))->keyBy(fn (array $entry): string => (string) data_get($entry, 'scenario'));
        $toEntries = collect((array) data_get($to, 'entries', []))->keyBy(fn (array $entry): string => (string) data_get($entry, 'scenario'));
        $scenarios = $fromEntries->keys()->merge($toEntries->keys())->unique()->sort()->values();
        $entries = [];
        $counts = [
            'added' => 0,
            'removed' => 0,
            'changed' => 0,
            'unchanged' => 0,
        ];

        foreach ($scenarios as $scenario) {
            $fromEntry = $fromEntries->get($scenario);
            $toEntry = $toEntries->get($scenario);
            $status = 'unchanged';
            $reasons = [];

            if ($fromEntry === null) {
                $status = 'added';
                $reasons[] = 'scenario_added';
            } elseif ($toEntry === null) {
                $status = 'removed';
                $reasons[] = 'scenario_removed';
            } else {
                if (data_get($fromEntry, 'artifact_fingerprint') !== data_get($toEntry, 'artifact_fingerprint')) {
                    $reasons[] = 'artifact_fingerprint_changed';
                }

                if (data_get($fromEntry, 'storyboard_html') !== data_get($toEntry, 'storyboard_html')) {
                    $reasons[] = 'storyboard_html_changed';
                }

                if (data_get($fromEntry, 'storyboard_pdf') !== data_get($toEntry, 'storyboard_pdf')) {
                    $reasons[] = 'storyboard_pdf_changed';
                }

                $status = $reasons === [] ? 'unchanged' : 'changed';
            }

            $counts[$status]++;
            $entries[] = [
                'scenario' => (string) $scenario,
                'status' => $status,
                'reasons' => $reasons,
                'from' => $this->qaDiffEntryPayload($fromEntry),
                'to' => $this->qaDiffEntryPayload($toEntry),
            ];
        }

        return [
            'schema' => 'x-change.claim-walkthrough.qa-diff.v1',
            'generated_at' => now()->toIso8601String(),
            'from' => [
                'source' => $fromPath,
                'run_id' => (string) data_get($from, 'run_id'),
            ],
            'to' => [
                'source' => $toPath,
                'run_id' => (string) data_get($to, 'run_id'),
            ],
            'counts' => $counts,
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $entry
     * @return array<string, mixed>|null
     */
    private function qaDiffEntryPayload(?array $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        return [
            'artifact_fingerprint' => data_get($entry, 'artifact_fingerprint'),
            'storyboard_html' => data_get($entry, 'storyboard_html'),
            'storyboard_pdf' => data_get($entry, 'storyboard_pdf'),
        ];
    }

    private function defaultQaAcceptanceReportPath(string $source): string
    {
        $directory = is_dir($source) ? $source : dirname($source);

        return rtrim($directory, '/').'/claim-ux-acceptance-report.md';
    }

    private function defaultQaReviewSummaryPath(string $source): string
    {
        $directory = is_dir($source) ? $source : dirname($source);

        return rtrim($directory, '/').'/claim-walkthrough-qa-review-summary.json';
    }

    private function defaultQaDiffReportPath(string $source): string
    {
        $directory = is_dir($source) ? $source : dirname($source);

        return rtrim($directory, '/').'/claim-ux-qa-diff-report.md';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderQaDiffMarkdown(array $payload): string
    {
        $lines = [
            '# Claim UX QA Diff Report',
            '',
            'Generated at: '.$this->qaBatchMarkdownText(data_get($payload, 'generated_at')),
            'From: '.$this->qaBatchMarkdownText(data_get($payload, 'from.run_id')).' (`'.$this->qaBatchMarkdownText(data_get($payload, 'from.source')).'`)',
            'To: '.$this->qaBatchMarkdownText(data_get($payload, 'to.run_id')).' (`'.$this->qaBatchMarkdownText(data_get($payload, 'to.source')).'`)',
            '',
            '## Summary',
            '',
            '- Added: '.data_get($payload, 'counts.added', 0),
            '- Removed: '.data_get($payload, 'counts.removed', 0),
            '- Changed: '.data_get($payload, 'counts.changed', 0),
            '- Unchanged: '.data_get($payload, 'counts.unchanged', 0),
            '',
            '## Changed Lanes',
            '',
        ];

        $changed = collect((array) data_get($payload, 'entries', []))
            ->filter(fn (array $entry): bool => data_get($entry, 'status') !== 'unchanged');

        if ($changed->isEmpty()) {
            $lines[] = '- No storyboard lane changed.';
        } else {
            foreach ($changed as $entry) {
                $lines[] = '- `'.data_get($entry, 'scenario').'` - '.data_get($entry, 'status').' ('.implode(', ', (array) data_get($entry, 'reasons', [])).')';
            }
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderQaAcceptanceMarkdown(array $payload): string
    {
        $review = (array) data_get($payload, 'review', []);
        $lines = [
            '# Claim UX Acceptance Report',
            '',
            'Generated at: '.$this->qaBatchMarkdownText(data_get($payload, 'generated_at')),
            'Source: '.$this->qaBatchMarkdownText(data_get($payload, 'source')),
            '',
            '## Summary',
            '',
            '- Accepted: '.(data_get($review, 'accepted') ? 'yes' : 'no'),
            '- Entries: '.data_get($review, 'entry_count', 0),
            '- Pass: '.data_get($review, 'counts.pass', 0),
            '- Needs fix: '.data_get($review, 'counts.needs_fix', 0),
            '- Blocker: '.data_get($review, 'counts.blocker', 0),
            '- Unreviewed: '.data_get($review, 'counts.unreviewed', 0),
            '',
            '## Visual Polish Priorities',
            '',
            ...$this->qaAcceptanceVisualPolishLines($review),
            '',
            '## Scenario Results',
            '',
            '| Priority | Scenario | Status | HTML | PDF | Notes |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        foreach ((array) data_get($review, 'entries', []) as $entry) {
            $lines[] = sprintf(
                '| %s | `%s` | %s | [HTML](%s) | [PDF](%s) | %s |',
                $this->qaAcceptanceTableCell(data_get($entry, 'priority')),
                $this->qaAcceptanceTableCell(data_get($entry, 'scenario')),
                $this->qaAcceptanceTableCell(data_get($entry, 'review.status', 'unreviewed')),
                $this->qaAcceptanceTableCell(data_get($entry, 'storyboard_html')),
                $this->qaAcceptanceTableCell(data_get($entry, 'storyboard_pdf')),
                $this->qaAcceptanceTableCell(data_get($entry, 'review.notes')),
            );
        }

        $lines = [
            ...$lines,
            '',
            '## Reviewer Notes By Status',
            '',
            ...$this->qaAcceptanceReviewerNoteLines($review),
            '',
        ];

        return implode(PHP_EOL, $lines);
    }

    private function qaAcceptanceTableCell(mixed $value): string
    {
        return str_replace('|', '\\|', $this->qaBatchMarkdownText($value));
    }

    /**
     * @param  array<string, mixed>  $review
     * @return list<string>
     */
    private function qaAcceptanceReviewerNoteLines(array $review): array
    {
        $entries = collect((array) data_get($review, 'entries', []));
        $lines = [];

        foreach (['blocker', 'needs_fix', 'unreviewed', 'pass'] as $status) {
            $matching = $entries->filter(fn (array $entry): bool => data_get($entry, 'review.status') === $status);

            if ($matching->isEmpty()) {
                continue;
            }

            $lines[] = '### '.str_replace('_', ' ', ucfirst($status));
            $lines[] = '';

            foreach ($matching as $entry) {
                $notes = trim((string) data_get($entry, 'review.notes'));
                $lines[] = '- `'.data_get($entry, 'scenario').'`: '.($notes !== '' ? $notes : 'No notes recorded.');
            }

            $lines[] = '';
        }

        return $lines !== [] ? $lines : ['- No reviewer notes were recorded.'];
    }

    /**
     * @param  array<string, mixed>  $review
     * @return list<string>
     */
    private function qaAcceptanceVisualPolishLines(array $review): array
    {
        $entries = collect((array) data_get($review, 'entries', []));
        $blockers = $entries->filter(fn (array $entry): bool => data_get($entry, 'review.status') === 'blocker');
        $needsFix = $entries->filter(fn (array $entry): bool => data_get($entry, 'review.status') === 'needs_fix');
        $unreviewed = $entries->filter(fn (array $entry): bool => data_get($entry, 'review.status') === 'unreviewed');
        $lines = [];

        if ($blockers->isNotEmpty()) {
            $lines[] = '- P0: Resolve blocker lanes before accepting this claim UX slice: '.$blockers->pluck('scenario')->implode(', ');
        }

        if ($needsFix->isNotEmpty()) {
            $lines[] = '- P1: Polish needs_fix lanes, focusing first on clipped text, unclear primary actions, and handler retry/cancel copy: '.$needsFix->pluck('scenario')->implode(', ');
        }

        if ($unreviewed->isNotEmpty()) {
            $lines[] = '- P2: Complete human review for unreviewed lanes before treating the batch as accepted: '.$unreviewed->pluck('scenario')->implode(', ');
        }

        if ($lines === []) {
            $lines[] = '- No blocking visual polish item was reported by the current worksheet.';
            $lines[] = '- Keep watching dense mobile screens: payout form spacing, handler capture surfaces, rider handoff, and success redirect copy.';
        }

        return $lines;
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = (string) ($this->option('base-url') ?: config('app.url', 'http://localhost'));

        return rtrim($baseUrl, '/');
    }

    private function resolveSlowMotion(): ?int
    {
        $slowMotion = filter_var($this->option('slow-mo'), FILTER_VALIDATE_INT);

        if ($slowMotion === false || $slowMotion < 0 || $slowMotion > 2000) {
            $this->error('The --slow-mo option must be an integer from 0 to 2000.');

            return null;
        }

        return $slowMotion;
    }

    private function resolveProfile(): ?string
    {
        $profile = (string) $this->option('profile');

        if (! in_array($profile, ['issuer', 'developer', 'qa', 'support'], true)) {
            $this->error('The --profile option must be one of: issuer, developer, qa, support.');

            return null;
        }

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderArtifactViewOptions(array $payload): void
    {
        $artifacts = $payload['artifacts'] ?? [];
        $viewOptions = data_get($artifacts, 'view_options', []);

        $this->line('View options:');

        if (is_array($viewOptions) && $viewOptions !== []) {
            $this->line('  Default PDF: '.data_get($viewOptions, 'default.path'));
            $this->line('  HTML storyboard: '.data_get($viewOptions, 'html.path'));
            $this->line('  Artifact folder: '.data_get($viewOptions, 'folder.path'));
            $this->line('  Open PDF: '.data_get($viewOptions, 'default.open_command'));
            $this->line('  Open folder: '.data_get($viewOptions, 'folder.open_command'));

            return;
        }

        $this->line('  Default PDF: '.data_get($artifacts, 'storyboard_pdf'));
        $this->line('  HTML storyboard: '.data_get($artifacts, 'storyboard_html'));
        $this->line('  Artifact folder: '.data_get($artifacts, 'root'));
        $this->line('  Report: '.data_get($artifacts, 'report_json'));
    }

    private function isLocalBaseUrl(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);
        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host)) {
            return false;
        }

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test');
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array{run_id: string, root: string, screenshots: string, storyboard_frames: string}  $run
     * @return array<string, mixed>
     */
    private function buildDryRun(
        array $scenario,
        array $run,
        string $baseUrl,
        ClaimWalkthroughStoryboardBuilder $storyboards
    ): array {
        $actions = [
            [
                'sequence' => 1,
                'event' => 'dry-run',
                'status' => 'passed',
                'message' => 'Storyboard scaffold created without launching a browser.',
            ],
        ];

        return $storyboards->build($scenario, $run, $actions, [
            'dry_run' => true,
            'base_url' => $baseUrl,
            'money_movement' => false,
            'form_flow_default_splash' => false,
        ]);
    }
}
