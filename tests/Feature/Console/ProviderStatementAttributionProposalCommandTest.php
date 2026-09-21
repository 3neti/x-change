<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

beforeEach(function () {
    config()->set('x-change.treasury.connections.netbank-primary.mode', 'required');
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);
    Http::preventStrayRequests();
});

it('builds the same sanitized review proposal without financial or provider writes', function () {
    ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'proposal-credit-local'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'proposal-credit-local',
        'gross_amount_minor' => 14_300,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 14_300,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now(),
        'settled_at' => now(),
        'verification_source' => 'test',
        'payload_hash' => hash('sha256', 'proposal-credit-payload'),
    ]);

    $statement = attributionProposalStatement([
        ['proposal-credit-local', 'credit', '143.00', 'PHP', 'settled', '2026-09-20T10:00:00+08:00', 'private payer'],
        ['proposal-credit-unmatched', 'credit', '25.00', 'PHP', 'settled', '2026-09-20T10:01:00+08:00', 'private account'],
        ['proposal-debit-unmatched', 'debit', '30.00', 'PHP', 'settled', '2026-09-20T10:02:00+08:00', 'must never leak'],
    ]);
    $checksum = hash_file('sha256', $statement);
    $manifest = attributionProposalManifest($statement, $checksum, rowLimitReached: true);
    $before = attributionProposalFinancialCounts();
    $arguments = [
        '--statement' => $statement,
        '--capture-manifest' => $manifest,
        '--connection' => 'netbank-primary',
        '--expected-statement-sha256' => $checksum,
        '--destination' => 'x-payout-recovery-test',
        '--json' => true,
    ];

    $firstExitCode = Artisan::call('x-change:continuity:propose-provider-attribution', $arguments);
    $firstOutput = Artisan::output();
    $first = json_decode($firstOutput, true);
    $secondExitCode = Artisan::call('x-change:continuity:propose-provider-attribution', $arguments);
    $secondOutput = Artisan::output();
    $second = json_decode($secondOutput, true);

    expect($firstExitCode)->toBe(Command::SUCCESS, $firstOutput)
        ->and($secondExitCode)->toBe(Command::SUCCESS, $secondOutput)
        ->and($first['status'])->toBe('review_required')
        ->and($first['continuity_proposal_hash'])->toBe($second['continuity_proposal_hash'])
        ->and($first['counts'])->toMatchArray([
            'matched_inflows' => 1,
            'unmatched_provider_credits' => 1,
            'unmatched_provider_debits' => 1,
        ])
        ->and($first['blockers'])->toContain(
            'financial_apply_not_supported',
            'maker_checker_authorization_not_present',
            'provider_attribution_not_authorized',
            'transaction_ownership_not_established',
            'provider_statement_sample_incomplete',
        )
        ->and($first['requires_maker_checker'])->toBeTrue()
        ->and($first['apply_supported'])->toBeFalse()
        ->and($first['read_only'])->toBeTrue()
        ->and($first['writes_database'])->toBeFalse()
        ->and($first['writes_storage'])->toBeFalse()
        ->and($first['writes_cache'])->toBeFalse()
        ->and($first['writes_journal'])->toBeFalse()
        ->and($first['writes_treasury'])->toBeFalse()
        ->and($first['provider_calls'])->toBeFalse()
        ->and($first['moves_money'])->toBeFalse()
        ->and($first['credits_accounts'])->toBeFalse()
        ->and($first['safe_to_reconcile'])->toBeFalse()
        ->and(attributionProposalFinancialCounts())->toBe($before)
        ->and($firstOutput)->not->toContain('proposal-credit-local')
        ->and($firstOutput)->not->toContain('proposal-credit-unmatched')
        ->and($firstOutput)->not->toContain('proposal-debit-unmatched')
        ->and($firstOutput)->not->toContain('private payer')
        ->and($firstOutput)->not->toContain('private account');

    Http::assertNothingSent();
});

it('fails closed when the reviewed checksum or capture manifest does not match', function () {
    $statement = attributionProposalStatement([
        ['proposal-secret-id', 'credit', '10.00', 'PHP', 'settled', '2026-09-20T10:00:00+08:00', 'secret description'],
    ]);
    $checksum = hash_file('sha256', $statement);
    $manifest = attributionProposalManifest($statement, $checksum);
    $before = attributionProposalFinancialCounts();

    $exitCode = Artisan::call('x-change:continuity:propose-provider-attribution', [
        '--statement' => $statement,
        '--capture-manifest' => $manifest,
        '--connection' => 'netbank-primary',
        '--expected-statement-sha256' => str_repeat('0', 64),
        '--destination' => 'x-payout-recovery-test',
        '--json' => true,
    ]);
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($result['status'])->toBe('rejected')
        ->and($result['apply_supported'])->toBeFalse()
        ->and($result['read_only'])->toBeTrue()
        ->and($result['provider_calls'])->toBeFalse()
        ->and($result['moves_money'])->toBeFalse()
        ->and(attributionProposalFinancialCounts())->toBe($before)
        ->and($output)->not->toContain('proposal-secret-id')
        ->and($output)->not->toContain('secret description');

    Http::assertNothingSent();
});

it('registers only the review proposal command and no continuity apply command', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('x-change:continuity:propose-provider-attribution')
        ->and($commands)->not->toHaveKey('x-change:continuity:apply');
});

/** @param list<list<string>> $rows */
function attributionProposalStatement(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'provider-proposal-');
    $handle = fopen($path, 'wb');
    fputcsv($handle, ['transaction_id', 'direction', 'amount', 'currency', 'status', 'occurred_at', 'description']);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

function attributionProposalManifest(string $statement, string $checksum, bool $rowLimitReached = false): string
{
    $rowCount = max(0, count(file($statement)) - 1);
    $path = tempnam(sys_get_temp_dir(), 'provider-proposal-manifest-');
    file_put_contents($path, json_encode([
        'schema' => 'x-change.provider-statement-snapshot.v1',
        'status' => 'captured_bounded',
        'connection_reference' => 'netbank-primary',
        'provider' => 'netbank',
        'currency' => 'PHP',
        'from' => '2026-09-01',
        'to' => '2026-09-20',
        'end_date_exclusive' => true,
        'row_count' => $rowCount,
        'row_limit' => $rowLimitReached ? $rowCount : max(5, $rowCount),
        'row_limit_reached' => $rowLimitReached,
        'statement_sha256' => $checksum,
        'statement_bytes' => filesize($statement),
        'statement_path' => 'private/redacted.csv',
        'created_at' => '2026-09-20T10:00:00+00:00',
    ], JSON_THROW_ON_ERROR));

    return $path;
}

/** @return array<string, int> */
function attributionProposalFinancialCounts(): array
{
    return [
        'provider_funding_observations' => DB::table('provider_funding_observations')->count(),
        'disbursement_reconciliations' => DB::table('disbursement_reconciliations')->count(),
        'voucher_collections' => DB::table('voucher_collections')->count(),
        'treasury_inventory_operations' => DB::table('treasury_inventory_operations')->count(),
        'treasury_position_operations' => DB::table('treasury_position_operations')->count(),
    ];
}
