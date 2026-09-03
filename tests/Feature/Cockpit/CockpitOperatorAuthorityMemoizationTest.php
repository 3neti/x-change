<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\RequestScopedSystemUserResolver;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Tests\Fakes\User;

/**
 * @return array<int, string>
 */
function captureOperatorAuthorizationQueries(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, '_operator_authorizations')) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

/**
 * @return array<int, string>
 */
function captureUserQueries(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'from "users"')) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

/**
 * @return array<int, string>
 */
function capturePartnerApiClientQueries(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'from "x_change_partner_api_clients"')) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

/**
 * @return array<int, string>
 */
function captureSchemaColumnQueries(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (
            str_contains($query->sql, 'pragma_table_xinfo')
            || str_contains($query->sql, 'information_schema.columns')
        ) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

it('memoizes system principal resolution within the request scoped resolver', function (): void {
    $system = User::query()->create([
        'name' => 'System Principal',
        'email' => 'request-scoped-system@example.test',
        'password' => 'password',
    ]);

    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);

    $resolver = app(SystemUserResolverContract::class);

    expect($resolver)->toBeInstanceOf(RequestScopedSystemUserResolver::class);

    $queries = captureUserQueries(function () use ($resolver, $system): void {
        expect($resolver->resolve()->is($system))->toBeTrue()
            ->and($resolver->resolve()->is($system))->toBeTrue();
    });

    expect($queries)->toHaveCount(1);
});

it('memoizes treasury account balance position reads within the request scoped read model', function (): void {
    $owner = User::query()->create([
        'name' => 'Treasury Account Holder',
        'email' => 'treasury-account-holder@example.test',
        'password' => 'password',
    ]);
    $principal = 'principal:test:'.$owner->getKey();

    $principalReferences = new class($principal) implements TreasuryPrincipalReferenceResolverContract
    {
        public function __construct(private readonly string $principal) {}

        public function resolve(mixed $owner): string
        {
            return $this->principal;
        }
    };
    $positions = new class($principal) implements TreasuryPositionReadModelContract
    {
        public int $principalReads = 0;

        public function __construct(private readonly string $principal) {}

        public function find(string $positionReference): ?TreasuryPositionData
        {
            return null;
        }

        public function forPrincipal(string $principalReference): array
        {
            $this->principalReads++;

            return [
                new TreasuryPositionData(
                    positionReference: 'position:test:client-funds',
                    principalReference: $this->principal,
                    mandateReference: 'mandate:test',
                    settlementResourceReference: 'resource:test',
                    provider: 'netbank',
                    connectionReference: 'netbank-primary',
                    currency: 'PHP',
                    decimalPlaces: 2,
                    purpose: TreasuryPositionPurpose::ClientFunds,
                    custodyMode: TreasuryCustodyMode::ProviderProjection,
                    legalProfile: 'test',
                    legalProfileVersion: 'v1',
                    balanceMinor: 12_300,
                    status: 'active',
                ),
            ];
        }

        public function forConnection(string $provider, string $connectionReference, string $currency): array
        {
            return [];
        }

        public function operationExists(string $operationReference): bool
        {
            return false;
        }
    };

    $readModel = new TreasuryAccountBalanceReadModel($principalReferences, $positions);

    expect($readModel->providerBalanceMinor($owner, 'netbank', 'PHP'))->toBe(12_300)
        ->and($readModel->providerBalanceMinor($owner, 'netbank', 'PHP'))->toBe(12_300)
        ->and($readModel->balanceMinor($owner, 'PHP'))->toBe(12_300)
        ->and($readModel->balanceMinor($owner, 'PHP'))->toBe(12_300)
        ->and($positions->principalReads)->toBe(2);
});

it('memoizes stored value partner api destination readiness within the scoped authority', function (): void {
    config()->set('x-change.partner_api.enabled', true);

    $issuer = User::query()->create([
        'name' => 'Stored Value Merchant',
        'email' => 'stored-value-merchant@example.test',
        'password' => 'password',
    ]);

    PartnerApiClient::query()->create([
        'reference' => 'partner-api:memoized-readiness',
        'oauth_client_id' => 'oauth-client:memoized-readiness',
        'name' => 'Stored Value Sandbox',
        'issuer_type' => $issuer->getMorphClass(),
        'issuer_id' => (string) $issuer->getKey(),
        'environment' => 'sandbox',
        'status' => 'active',
        'scopes' => ['stored-value:spend'],
        'mandate' => [
            'stored_value_spend' => [
                'enabled' => true,
                'currencies' => ['PHP'],
                'maximum_amount_minor' => 10_000,
                'daily_amount_minor' => 100_000,
            ],
        ],
        'activated_at' => now(),
    ]);

    $authority = app(StoredValueDestinationAuthorityContract::class);

    $queries = capturePartnerApiClientQueries(function () use ($authority): void {
        expect($authority->isReady())->toBeTrue()
            ->and($authority->isReady())->toBeTrue();
    });

    expect($queries)->toHaveCount(1);
});

it('memoizes stored value holder readiness schema checks within the scoped authority', function (): void {
    config()->set('x-change.onboarding.issuer_model', User::class);

    $authority = app(StoredValueHolderAuthorityContract::class);

    $queries = captureSchemaColumnQueries(function () use ($authority): void {
        $first = $authority->isReady();
        $second = $authority->isReady();

        expect($second)->toBe($first);
    });

    expect($queries)->toHaveCount(1);
});

it('memoizes commercial operator capabilities within the request scoped authority instance', function (): void {
    $operator = User::query()->create([
        'name' => 'Commercial Operator',
        'email' => 'commercial-operator@example.test',
        'password' => 'password',
    ]);

    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::ViewCommercialControls->value,
        'authorization_reference' => 'memoization-test:commercial:view',
        'valid_from' => now()->subMinute(),
    ]);

    $authority = app(CommercialOperatorAuthorityContract::class);

    $queries = captureOperatorAuthorizationQueries(function () use ($authority, $operator): void {
        expect($authority->allows($operator, CommercialOperatorCapability::ViewCommercialControls))->toBeTrue()
            ->and($authority->allows($operator, CommercialOperatorCapability::ManageOfferings))->toBeFalse()
            ->and($authority->allows($operator, CommercialOperatorCapability::ViewCommercialControls))->toBeTrue();
    });

    expect($queries)->toHaveCount(2)
        ->and(implode("\n", $queries))->toContain('sqlite_master')
        ->and(implode("\n", $queries))->toContain('select "capability"');
});

it('memoizes partner api operator capabilities within the request scoped authority instance', function (): void {
    $operator = User::query()->create([
        'name' => 'Partner API Operator',
        'email' => 'partner-api-operator@example.test',
        'password' => 'password',
    ]);

    PartnerApiOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => PartnerApiOperatorCapability::ViewClients->value,
        'authorization_reference' => 'memoization-test:partner-api:view',
        'valid_from' => now()->subMinute(),
    ]);

    $authority = app(PartnerApiOperatorAuthority::class);

    $queries = captureOperatorAuthorizationQueries(function () use ($authority, $operator): void {
        expect($authority->allows($operator, PartnerApiOperatorCapability::ViewClients))->toBeTrue()
            ->and($authority->allows($operator, PartnerApiOperatorCapability::ManageSandboxClients))->toBeFalse()
            ->and($authority->allows($operator, PartnerApiOperatorCapability::ViewClients))->toBeTrue();
    });

    expect($queries)->toHaveCount(2)
        ->and(implode("\n", $queries))->toContain('sqlite_master')
        ->and(implode("\n", $queries))->toContain('select "capability"');
});

it('memoizes treasury operator authority checks and continues to deny the system principal', function (): void {
    $system = User::query()->create([
        'name' => 'System Principal',
        'email' => 'system-principal@example.test',
        'password' => 'password',
    ]);
    $operator = User::query()->create([
        'name' => 'Treasury Operator',
        'email' => 'treasury-operator@example.test',
        'password' => 'password',
    ]);

    app()->instance(SystemUserResolverContract::class, new class($system) implements SystemUserResolverContract
    {
        public function __construct(private readonly User $system) {}

        public function resolve(): User
        {
            return $this->system;
        }
    });

    TreasuryOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => TreasuryOperatorCapability::ViewAccountGrants->value,
        'authorization_reference' => 'memoization-test:treasury:view',
        'valid_from' => now()->subMinute(),
    ]);
    TreasuryOperatorAuthorization::query()->create([
        'operator_type' => $system->getMorphClass(),
        'operator_id' => $system->getKey(),
        'capability' => TreasuryOperatorCapability::ViewAccountGrants->value,
        'authorization_reference' => 'memoization-test:treasury:system',
        'valid_from' => now()->subMinute(),
    ]);

    $authority = app(TreasuryOperatorAuthority::class);

    $queries = captureOperatorAuthorizationQueries(function () use ($authority, $operator, $system): void {
        expect($authority->allows($system, TreasuryOperatorCapability::ViewAccountGrants))->toBeFalse()
            ->and($authority->allows($operator, TreasuryOperatorCapability::ViewAccountGrants))->toBeTrue()
            ->and($authority->allows($operator, TreasuryOperatorCapability::ExecuteAccountGrants))->toBeFalse()
            ->and($authority->allows($operator, TreasuryOperatorCapability::ViewAccountGrants))->toBeTrue();
    });

    expect($queries)->toHaveCount(2)
        ->and(implode("\n", $queries))->toContain('sqlite_master')
        ->and(implode("\n", $queries))->toContain('select "capability"');
});
