<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactCache;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughArtifactStore;
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
    ): int {
        if ($this->option('list')) {
            return $this->listScenarios($scenarios);
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
                'og_source' => null,
            ],
            'count' => 1,
            'prefix' => 'QA',
            'mask' => '****',
            'ttl' => null,
            'metadata' => [
                'issuer_id' => $issuer,
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
