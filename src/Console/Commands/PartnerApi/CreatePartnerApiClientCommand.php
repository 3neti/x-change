<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\PartnerApi;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use RuntimeException;

final class CreatePartnerApiClientCommand extends Command
{
    protected $signature = 'x-change:partner-api:client
        {name : Human-readable client name}
        {--issuer= : Stable issuer Account identity}
        {--issuer-column=email : Issuer model column used for lookup}
        {--environment=sandbox : sandbox or production}
        {--scope=* : OAuth scope; repeat for multiple scopes}
        {--currency=* : Allowed currency; repeat for multiple currencies}
        {--rail=* : Allowed settlement rail; repeat for multiple rails}
        {--maximum-amount-minor= : Per-issuance principal ceiling in minor units}
        {--daily-principal-minor= : Daily aggregate principal ceiling in minor units}
        {--allow-unbound : Permit unbound Pay Codes}
        {--confirm-production : Confirm creation of production credentials}
        {--json : Emit machine-readable credentials once}';

    protected $description = 'Create a scoped OAuth client bound to one X-Change issuer Account.';

    public function handle(CreatePartnerApiClient $create): int
    {
        $environment = (string) $this->option('environment');

        if ($environment === 'production' && ! (bool) $this->option('confirm-production')) {
            $this->components->error('Production Partner API credentials require --confirm-production.');

            return self::FAILURE;
        }

        $issuer = $this->resolveIssuer();
        $mandate = array_filter([
            'currencies' => $this->stringOptions('currency'),
            'settlement_rails' => $this->stringOptions('rail'),
            'maximum_amount_minor' => $this->integerOption('maximum-amount-minor'),
            'daily_principal_limit_minor' => $this->integerOption('daily-principal-minor'),
            'unbound_pay_codes' => (bool) $this->option('allow-unbound'),
        ], static fn (mixed $value, string $key): bool => $key === 'unbound_pay_codes'
            || (is_array($value) ? $value !== [] : $value !== null), ARRAY_FILTER_USE_BOTH);

        $credential = $create->handle(
            name: (string) $this->argument('name'),
            issuer: $issuer,
            environment: $environment,
            scopes: $this->stringOptions('scope'),
            mandate: $mandate,
        );
        $payload = [
            'schema' => 'x-change.partner-api-credential.v1',
            ...$credential->toArray(),
            'token_endpoint' => url('/oauth/token'),
            'secret_display' => 'one_time_only',
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Partner API client created');
            $this->table(['Field', 'Value'], [
                ['Reference', $credential->reference],
                ['Client ID', $credential->client_id],
                ['Client Secret', $credential->client_secret],
                ['Token Endpoint', url('/oauth/token')],
                ['Environment', $credential->environment],
                ['Scopes', implode(', ', $credential->scopes)],
            ]);
            $this->components->warn('Store the client secret now. X-Change will not display it again.');
        }

        return self::SUCCESS;
    }

    protected function resolveIssuer(): Model
    {
        $identity = trim((string) $this->option('issuer'));

        if ($identity === '') {
            throw new RuntimeException('The --issuer option is required.');
        }

        $model = config('auth.providers.users.model');
        $column = trim((string) $this->option('issuer-column'));

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('The configured authentication user model is invalid.');
        }

        if (! in_array($column, ['id', 'email', 'mobile'], true)) {
            throw new RuntimeException('The issuer column must be id, email, or mobile.');
        }

        return $model::query()->where($column, $identity)->firstOrFail();
    }

    /** @return list<string> */
    protected function stringOptions(string $name): array
    {
        return collect((array) $this->option($name))
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : max(0, (int) $value);
    }
}
