<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\Continuity\InstanceBalanceReportTextRenderer;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;

function prepareBalanceReportFixture(bool $withUsdConnection = true): User
{
    CarbonImmutable::setTestNow('2026-09-27T08:00:00+00:00');
    config()->set('x-change.instance.id', 'x-change-production-beta-rehearsal');
    config()->set('x-change.funding.provider_balance_max_age_seconds', 300);
    $system = enableNetbankTreasuryForTests();
    config()->set('x-change.deployment.runtime_tier', 'production');

    if ($withUsdConnection) {
        $connections = (array) config('x-change.treasury.connections');
        $connections['netbank-usd'] = array_replace(
            $connections['netbank-primary'],
            [
                'currency' => 'USD',
                'inventory_reference' => 'inventory:netbank:usd',
                'settlement_resource_reference' => 'resource:netbank:usd',
            ],
        );
        config()->set('x-change.treasury.connections', $connections);
        app()->forgetInstance(TreasuryProviderConnectionCatalog::class);
        app()->forgetInstance(TreasuryProvisioningService::class);
    }

    app(TreasuryProvisioningService::class)->provision(
        $withUsdConnection ? ['netbank-primary', 'netbank-usd'] : ['netbank-primary'],
    );
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'balance-report:inventory:php',
        externalReference: 'resource:netbank:corporate-vca',
    ));

    if ($withUsdConnection) {
        $inventory->registerInventory(new TreasuryInventoryData(
            inventoryReference: 'inventory:netbank:usd',
            resourceType: 'cash_at_bank',
            currency: 'USD',
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'balance-report:inventory:usd',
            externalReference: 'resource:netbank:usd',
        ));
    }

    ProviderBalanceSnapshot::query()->create([
        'provider_code' => 'netbank',
        'balance_key' => 'netbank_php_source',
        'scope_key' => 'global',
        'balance_minor' => 0,
        'available_balance_minor' => 0,
        'currency' => 'PHP',
        'account_reference_masked' => '113001000019',
        'fetched_at' => now(),
        'refresh_status' => 'fresh',
    ]);

    if ($withUsdConnection) {
        ProviderBalanceSnapshot::query()->create([
            'provider_code' => 'netbank',
            'balance_key' => 'netbank_usd_source',
            'scope_key' => 'global',
            'balance_minor' => 0,
            'available_balance_minor' => 0,
            'currency' => 'USD',
            'account_reference_masked' => '113001000027',
            'fetched_at' => now(),
            'refresh_status' => 'fresh',
        ]);
    }

    return $system;
}

it('produces deterministic masked multi-currency balance evidence without any write or provider call', function (): void {
    $system = prepareBalanceReportFixture();
    $accountWithoutWallet = User::query()->create([
        'name' => 'No Wallet Person',
        'email' => 'no-wallet-person@example.test',
        'mobile' => '09171234567',
        'password' => 'not-a-login-credential',
    ]);
    $walletCount = Wallet::query()->count();
    $positionCount = TreasuryPosition::query()->count();
    $inventoryCount = TreasuryInventory::query()->count();
    $snapshotCount = ProviderBalanceSnapshot::query()->count();
    $auditCount = fakeAuditLogger()->count();
    $mutatingQueries = [];

    DB::listen(function (QueryExecuted $query) use (&$mutatingQueries): void {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql) === 1) {
            $mutatingQueries[] = $query->sql;
        }
    });

    $arguments = [
        '--as-of' => '2026-09-27T08:00:00+00:00',
        '--json' => true,
    ];

    expect(Artisan::call('x-change:continuity:balance-report', $arguments))->toBe(0);
    $firstOutput = Artisan::output();
    $first = json_decode($firstOutput, true, flags: JSON_THROW_ON_ERROR);
    expect(Artisan::call('x-change:continuity:balance-report', $arguments))->toBe(0);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $connections = collect($first['report']['treasury_connections'])->keyBy('reference');
    $accounts = collect($first['report']['accounts'])->keyBy('account_reference');
    $unprovisioned = $accounts->first(
        static fn (array $account): bool => data_get($account, 'identifier.email') === 'n***@***',
    );

    expect($first['schema'])->toBe('x-change.instance-balance-report-envelope.v1')
        ->and($first['status'])->toBe('complete')
        ->and($first['report']['schema'])->toBe('x-change.instance-balance-report.v1')
        ->and($first['report']['as_of'])->toBe('2026-09-27T08:00:00+00:00')
        ->and($first['report']['safety']['read_only'])->toBeTrue()
        ->and($first['report']['safety']['writes_database'])->toBeFalse()
        ->and($first['report']['safety']['writes_cache'])->toBeFalse()
        ->and($first['report']['safety']['writes_journal'])->toBeFalse()
        ->and($first['report']['safety']['provider_calls'])->toBeFalse()
        ->and($first['report']['safety']['moves_money'])->toBeFalse()
        ->and($first['report']['safety']['transfers_balances'])->toBeFalse()
        ->and($first['report']['controls']['all_inventory_equals_positions'])->toBeTrue()
        ->and($connections)->toHaveKeys(['netbank-primary', 'netbank-usd'])
        ->and($connections['netbank-primary']['currency'])->toBe('PHP')
        ->and($connections['netbank-usd']['currency'])->toBe('USD')
        ->and($connections['netbank-primary']['control']['inventory_equals_positions'])->toBeTrue()
        ->and($connections['netbank-usd']['control']['inventory_equals_positions'])->toBeTrue()
        ->and($first['report_sha256'])->toBe($second['report_sha256'])
        ->and($first['artifact_checksums'])->toBe($second['artifact_checksums'])
        ->and($first['artifact_checksums']['canonical_report_json_sha256'])->toBe(hash(
            'sha256',
            app(CanonicalKeepsakeJson::class)->encode($first['report']),
        ))
        ->and($first['artifact_checksums']['human_report_text_sha256'])->toBe(hash(
            'sha256',
            app(InstanceBalanceReportTextRenderer::class)->render($first['report']),
        ))
        ->and($unprovisioned)->not->toBeNull()
        ->and(data_get($unprovisioned, 'identifier.name'))->toBe('N***')
        ->and(data_get($unprovisioned, 'identifier.email'))->toBe('n***@***')
        ->and(data_get($unprovisioned, 'balances.0.client_funds_minor'))->toBe(0)
        ->and($firstOutput)->not->toContain($system->email)
        ->and($firstOutput)->not->toContain($accountWithoutWallet->email)
        ->and($firstOutput)->not->toContain('09171234567')
        ->and($firstOutput)->not->toContain('113001000019')
        ->and($firstOutput)->toContain('********0019')
        ->and($mutatingQueries)->toBe([])
        ->and(Wallet::query()->count())->toBe($walletCount)
        ->and(TreasuryPosition::query()->count())->toBe($positionCount)
        ->and(TreasuryInventory::query()->count())->toBe($inventoryCount)
        ->and(ProviderBalanceSnapshot::query()->count())->toBe($snapshotCount)
        ->and(fakeAuditLogger()->count())->toBe($auditCount);

    fakePayoutProvider()->assertNoDisbursementAttempted();
    expect(fakePayoutProvider()->checkStatusCallCount)->toBe(0);
});

it('fails closed and reports stale snapshots and conservation variances without normalizing them', function (): void {
    $system = prepareBalanceReportFixture(withUsdConnection: false);
    ProviderBalanceSnapshot::query()->update([
        'fetched_at' => now()->subHour(),
    ]);
    $unattributed = TreasuryPosition::query()
        ->whereMorphedTo('principal', $system)
        ->where('purpose', TreasuryPositionPurpose::LegacyUnattributed)
        ->sole();
    app(TreasuryPositionOperationContract::class)->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'balance-report:mismatch:position',
        destinationPositionReference: $unattributed->position_reference,
        amountMinor: 123,
        currency: 'PHP',
        idempotencyKey: 'balance-report:mismatch:position',
        externalReference: 'balance-report-test',
    ));
    $counts = [
        'wallets' => Wallet::query()->count(),
        'positions' => TreasuryPosition::query()->count(),
        'inventories' => TreasuryInventory::query()->count(),
    ];

    expect(Artisan::call('x-change:continuity:balance-report', [
        '--as-of' => '2026-09-27T08:00:00+00:00',
        '--json' => true,
    ]))->toBe(1);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['status'])->toBe('incomplete')
        ->and($payload['report']['blockers'])->toContain('provider_snapshot_stale:netbank-primary')
        ->and($payload['report']['blockers'])->toContain('inventory_position_mismatch:netbank-primary')
        ->and(data_get(
            $payload,
            'report.treasury_connections.0.control.difference_minor',
        ))->toBe(-123)
        ->and(Wallet::query()->count())->toBe($counts['wallets'])
        ->and(TreasuryPosition::query()->count())->toBe($counts['positions'])
        ->and(TreasuryInventory::query()->count())->toBe($counts['inventories']);
});

it('provides evidence-only help and a human-readable report from the same read model', function (): void {
    prepareBalanceReportFixture(withUsdConnection: false);

    $this->artisan('help', ['command_name' => 'x-change:continuity:balance-report'])
        ->expectsOutputToContain('never')
        ->expectsOutputToContain('provider')
        ->assertSuccessful();

    $exitCode = Artisan::call('x-change:continuity:balance-report', [
        '--as-of' => '2026-09-27T08:00:00+00:00',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('X-Change Instance Balance Report')
        ->and($output)->toContain('Client Funds')
        ->and($output)->toContain('Evidence only.')
        ->and($output)->toContain('Canonical report JSON SHA-256:')
        ->and($output)->toContain('Human report text SHA-256:')
        ->and($output)->toContain('Report SHA-256:');
});

it('provides an explicitly authorized private client funds roster with an as-of timestamp', function (): void {
    prepareBalanceReportFixture(withUsdConnection: false);
    $account = User::query()->create([
        'name' => 'Amelia Hurtado',
        'email' => 'amelia@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $account->forceFill(['mobile' => '09175180722'])->save();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $account,
        ['netbank-primary'],
    );
    treasuryClientFundsLedger($account)->deposit(1_557_230, [
        'source' => 'client-funds-roster-test',
    ]);
    $walletCount = Wallet::query()->count();
    $positionCount = TreasuryPosition::query()->count();
    $auditCount = fakeAuditLogger()->count();
    $mutatingQueries = [];

    DB::listen(function (QueryExecuted $query) use (&$mutatingQueries): void {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql) === 1) {
            $mutatingQueries[] = $query->sql;
        }
    });

    expect(Artisan::call('x-change:continuity:client-funds-roster', [
        '--connection' => 'netbank-primary',
        '--authorization-reference' => 'OPS-2026-09-27-001',
        '--confirm-sensitive-output' => true,
    ]))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('PRIVATE REPORT')
        ->and($output)->toContain('As of: 2026-09-27T08:00:00+00:00')
        ->and($output)->toContain('Name')
        ->and($output)->toContain('Mobile Number')
        ->and($output)->toContain('Client Funds')
        ->and($output)->toContain('Amelia Hurtado')
        ->and($output)->toContain('09175180722')
        ->and($output)->toContain('₱15,572.30')
        ->and($output)->not->toContain('amelia@example.test')
        ->and($output)->toContain('Roster SHA-256:')
        ->and($mutatingQueries)->toBe([])
        ->and(Wallet::query()->count())->toBe($walletCount)
        ->and(TreasuryPosition::query()->count())->toBe($positionCount)
        ->and(fakeAuditLogger()->count())->toBe($auditCount);

    fakePayoutProvider()->assertNoDisbursementAttempted();
    expect(fakePayoutProvider()->checkStatusCallCount)->toBe(0);
});

it('refuses to disclose the private roster without scope and explicit acknowledgement', function (): void {
    prepareBalanceReportFixture(withUsdConnection: false);

    expect(Artisan::call('x-change:continuity:client-funds-roster', [
        '--connection' => 'netbank-primary',
        '--authorization-reference' => 'OPS-2026-09-27-001',
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('--confirm-sensitive-output');

    expect(Artisan::call('x-change:continuity:client-funds-roster', [
        '--connection' => 'netbank-primary',
        '--confirm-sensitive-output' => true,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('--authorization-reference');

    expect(Artisan::call('x-change:continuity:client-funds-roster', [
        '--connection' => 'paynamics-primary',
        '--authorization-reference' => 'OPS-2026-09-27-001',
        '--confirm-sensitive-output' => true,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('Unknown or disabled Treasury connections');
});
