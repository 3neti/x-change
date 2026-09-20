<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('provider-statements');
    config()->set('filesystems.disks.provider-statements', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/provider-statements'),
        'throw' => false,
    ]);
    config()->set('x-change.continuity.provider_statement', [
        'disk' => 'provider-statements',
        'directory' => 'x-change/provider-statements',
        'max_rows' => 5,
        'max_range_days' => 31,
        'max_bytes' => 1_048_576,
    ]);
    config()->set('x-change.deployment.runtime_tier', 'local');
    config()->set('x-change.treasury.connections.netbank-primary.mode', 'required');
    config()->set('payment-gateway.netbank.funding', [
        'api_url' => 'https://api.netbank.test',
        'token_url' => 'https://auth.netbank.test/oauth2/token',
        'client_id' => 'sensitive-client-id',
        'client_secret' => 'sensitive-client-secret',
        'corporate_account_number' => 'sensitive-account-number',
        'account_transactions_endpoint' => '/v1/accounts/{account_number}/transactions',
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => 15,
        'account_history' => [
            'page_limit' => 2,
            'maximum_pages' => 3,
            'maximum_rows' => 5,
            'maximum_range_days' => 31,
        ],
    ]);
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);
});

it('captures normalized private statement evidence without financial or journal writes', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://auth.netbank.test/oauth2/token') {
            return Http::response([
                'access_token' => 'sensitive-access-token',
                'expires_in' => 3600,
            ]);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match ((int) ($query['offset'] ?? -1)) {
            0 => Http::response(['result' => [
                providerCaptureTransaction('provider-transaction-one', 'Credit', '14300'),
                providerCaptureTransaction('provider-transaction-two', 'Debit', '2500'),
            ]]),
            default => Http::response(['result' => []]),
        };
    });
    $before = providerCaptureFinancialCounts();

    $exitCode = Artisan::call('x-change:continuity:capture-provider-statement', [
        '--connection' => 'netbank-primary',
        '--from' => '2026-09-01',
        '--to' => '2026-09-21',
        '--max-rows' => '5',
        '--confirm-sensitive-capture' => true,
        '--json' => true,
    ]);
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(Command::SUCCESS, $output)
        ->and($result)->toMatchArray([
            'status' => 'captured_bounded',
            'connection_reference' => 'netbank-primary',
            'provider' => 'netbank',
            'currency' => 'PHP',
            'from' => '2026-09-01',
            'to' => '2026-09-21',
            'row_count' => 2,
            'row_limit' => 5,
            'row_limit_reached' => false,
            'provider_calls' => true,
            'writes_storage' => true,
            'writes_database' => false,
            'writes_journal' => false,
            'moves_money' => false,
            'safe_to_reconcile' => false,
        ])
        ->and(providerCaptureFinancialCounts())->toBe($before)
        ->and($output)->not->toContain('sensitive-account-number')
        ->not->toContain('sensitive-access-token')
        ->not->toContain('provider-transaction-one');

    $statementPath = $result['artifact']['statement_path'];
    $manifestPath = $result['artifact']['manifest_path'];
    Storage::disk('provider-statements')->assertExists($statementPath);
    Storage::disk('provider-statements')->assertExists($manifestPath);

    expect(Storage::disk('provider-statements')->get($statementPath))->toBe(
        "transaction_id,direction,amount,currency,status,occurred_at\n"
        ."provider-transaction-one,credit,143.00,PHP,settled,2026-09-20T10:00:00+00:00\n"
        ."provider-transaction-two,debit,25.00,PHP,settled,2026-09-20T10:00:00+00:00\n",
    );
    $manifest = json_decode(Storage::disk('provider-statements')->get($manifestPath), true);

    expect($manifest)->not->toHaveKey('account_number')
        ->and($manifest)->not->toHaveKey('transactions')
        ->and($manifest['statement_sha256'])->toBe(hash('sha256', Storage::disk('provider-statements')->get($statementPath)));

    Http::assertSentCount(3);
});

it('fails closed without confirmation or when provider data is malformed', function () {
    $missingConfirmation = Artisan::call('x-change:continuity:capture-provider-statement', [
        '--connection' => 'netbank-primary',
        '--from' => '2026-09-01',
        '--to' => '2026-09-21',
        '--max-rows' => '5',
        '--json' => true,
    ]);

    expect($missingConfirmation)->toBe(Command::FAILURE)
        ->and(Storage::disk('provider-statements')->allFiles())->toBe([]);
    Http::assertNothingSent();

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'sensitive-access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/accounts/*' => Http::response(['result' => [
            providerCaptureTransaction('private-transaction-id', 'Sideways', '2500'),
        ]]),
    ]);

    $malformed = Artisan::call('x-change:continuity:capture-provider-statement', [
        '--connection' => 'netbank-primary',
        '--from' => '2026-09-01',
        '--to' => '2026-09-21',
        '--max-rows' => '5',
        '--confirm-sensitive-capture' => true,
        '--json' => true,
    ]);
    $output = Artisan::output();

    expect($malformed)->toBe(Command::FAILURE)
        ->and(Storage::disk('provider-statements')->allFiles())->toBe([])
        ->and($output)->not->toContain('private-transaction-id')
        ->not->toContain('sensitive-access-token');
});

it('rejects local statement storage in durable runtime tiers before calling the provider', function () {
    config()->set('x-change.deployment.runtime_tier', 'production');

    $exitCode = Artisan::call('x-change:continuity:capture-provider-statement', [
        '--connection' => 'netbank-primary',
        '--from' => '2026-09-01',
        '--to' => '2026-09-21',
        '--max-rows' => '5',
        '--confirm-sensitive-capture' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('durable non-local private disk')
        ->and(Storage::disk('provider-statements')->allFiles())->toBe([]);
    Http::assertNothingSent();
});

/** @return array<string, mixed> */
function providerCaptureTransaction(string $transactionId, string $type, string $amountMinor): array
{
    return [
        'transaction_id' => $transactionId,
        'type' => $type,
        'amount' => ['cur' => 'PHP', 'num' => $amountMinor],
        'status' => 'Settled',
        'date' => '2026-09-20T10:00:00.000Z',
        'description' => 'private provider narrative that must not be copied',
    ];
}

/** @return array<string, int> */
function providerCaptureFinancialCounts(): array
{
    return [
        'provider_funding_observations' => DB::table('provider_funding_observations')->count(),
        'disbursement_reconciliations' => DB::table('disbursement_reconciliations')->count(),
        'voucher_collections' => DB::table('voucher_collections')->count(),
        'treasury_inventory_operations' => DB::table('treasury_inventory_operations')->count(),
        'treasury_position_operations' => DB::table('treasury_position_operations')->count(),
    ];
}
