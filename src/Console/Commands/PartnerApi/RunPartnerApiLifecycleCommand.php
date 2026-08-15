<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\PartnerApi;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class RunPartnerApiLifecycleCommand extends Command
{
    protected $signature = 'x-change:partner-api:run
        {--base-url= : X-Change host URL}
        {--client-id= : OAuth client ID}
        {--client-secret= : OAuth client secret; omit for a hidden prompt}
        {--scenario=contract : contract or issue-and-cancel}
        {--amount=1.00 : Major-unit PHP test amount}
        {--mobile=09171234567 : Bound recipient mobile for the acceptance Pay Code}
        {--confirm-financial-mutation : Permit real issuance and terminal release}
        {--json : Emit a stable machine-readable report}';

    protected $description = 'Exercise X-Change through its public Partner API over HTTP.';

    public function handle(): int
    {
        $scenario = (string) $this->option('scenario');

        if (! in_array($scenario, ['contract', 'issue-and-cancel'], true)) {
            throw new RuntimeException('Scenario must be contract or issue-and-cancel.');
        }

        if ($scenario === 'issue-and-cancel' && ! (bool) $this->option('confirm-financial-mutation')) {
            $this->components->error('issue-and-cancel requires --confirm-financial-mutation.');

            return self::FAILURE;
        }

        $baseUrl = rtrim($this->requiredValue('base-url', 'Base URL'), '/');
        $clientId = $this->requiredValue('client-id', 'OAuth client ID');
        $clientSecret = $this->secretValue();
        $scopes = $scenario === 'contract'
            ? 'capabilities:read'
            : 'capabilities:read pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:cancel';
        $token = Http::asForm()->acceptJson()->post($baseUrl.'/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => $scopes,
        ])->throw()->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('OAuth token response did not contain an access token.');
        }

        $api = Http::baseUrl($baseUrl.'/api/partner/v1')
            ->acceptJson()
            ->withToken($token);
        $report = [
            'schema' => 'x-change.partner-api-lifecycle-run.v1',
            'success' => true,
            'scenario' => $scenario,
            'safety' => [
                'transport' => 'http',
                'direct_action_calls' => false,
                'financial_mutation_confirmed' => $scenario === 'issue-and-cancel',
                'provider_calls' => false,
                'commercial_charges_refunded' => false,
            ],
            'oauth' => ['grant_type' => 'client_credentials', 'token_received' => true],
            'capabilities' => $api->get('/capabilities')->throw()->json('data'),
        ];

        if ($scenario === 'issue-and-cancel') {
            $report['lifecycle'] = $this->issueAndCancel($api);
        }

        $this->render($report);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    protected function issueAndCancel(PendingRequest $api): array
    {
        $payload = $this->payload();
        $idempotencyKey = 'partner-acceptance-'.Str::lower((string) Str::ulid());
        $correlationId = 'partner-acceptance-'.Str::uuid();
        $estimate = $api->post('/pay-code-estimates', $payload)->throw()->json('data');
        $issued = $api->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
            'X-Correlation-ID' => $correlationId,
        ])->post('/pay-codes', $payload)->throw()->json('data');
        $code = data_get($issued, 'code');

        if (! is_string($code) || $code === '') {
            throw new RuntimeException('Issuance response did not contain a Pay Code.');
        }

        return [
            'estimate' => $estimate,
            'issuance' => $issued,
            'status_before_cancellation' => $api->get('/pay-codes/'.$code)->throw()->json('data'),
            'cancellation' => $api->post('/pay-codes/'.$code.'/cancellation', [
                'reason' => 'Partner API lifecycle acceptance cleanup.',
            ])->throw()->json('data'),
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId,
        ];
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [
            'cash' => [
                'amount' => (float) $this->option('amount'),
                'currency' => 'PHP',
                'settlement_rail' => 'INSTAPAY',
                'validation' => ['mobile' => (string) $this->option('mobile')],
            ],
            'inputs' => ['fields' => []],
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => ['message' => 'Partner API lifecycle acceptance', 'url' => null, 'splash' => null],
        ];
    }

    protected function requiredValue(string $option, string $label): string
    {
        $value = trim((string) $this->option($option));

        if ($value === '' && $this->input->isInteractive()) {
            $value = trim((string) $this->ask($label));
        }

        if ($value === '') {
            throw new RuntimeException("{$label} is required.");
        }

        return $value;
    }

    protected function secretValue(): string
    {
        $secret = trim((string) $this->option('client-secret'));

        if ($secret === '' && $this->input->isInteractive()) {
            $secret = trim((string) $this->secret('OAuth client secret'));
        }

        if ($secret === '') {
            throw new RuntimeException('OAuth client secret is required.');
        }

        return $secret;
    }

    /** @param array<string, mixed> $report */
    protected function render(array $report): void
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->components->info('Partner API lifecycle passed through HTTP');
        $this->line('Scenario: '.(string) $report['scenario']);
        $this->line('OAuth: client credentials token received');

        if (isset($report['lifecycle'])) {
            $this->line('Pay Code: '.(string) data_get($report, 'lifecycle.issuance.code'));
            $this->line('Terminal result: '.(string) data_get($report, 'lifecycle.cancellation.status'));
        }
    }
}
