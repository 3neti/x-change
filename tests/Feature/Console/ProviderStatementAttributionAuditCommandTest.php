<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

beforeEach(function () {
    config()->set('x-change.treasury.connections.netbank-primary.mode', 'required');
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);
});

it('audits a normalized provider statement without writes or provider calls', function () {
    ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'credit-local'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'credit-local',
        'gross_amount_minor' => 14_300,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 14_300,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now(),
        'settled_at' => now(),
        'verification_source' => 'test',
        'payload_hash' => hash('sha256', 'credit-payload'),
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_code' => 'TEST-AUDIT',
        'provider' => 'netbank',
        'provider_transaction_id' => 'debit-local',
        'status' => 'succeeded',
        'amount' => '25.00',
        'currency' => 'PHP',
    ]);

    $path = providerStatementCsv([
        ['credit-local', 'credit', '143.00', 'PHP', 'settled', '2026-09-20T10:00:00+08:00', 'private payer name'],
        ['debit-local', 'debit', '25.00', 'PHP', 'settled', '2026-09-20T10:01:00+08:00', 'private account number'],
        ['external-debit', 'debit', '19804.33', 'PHP', 'settled', '2026-09-20T10:02:00+08:00', 'must never leak'],
        ['external-credit', 'credit', '1.00', 'PHP', 'settled', '2026-09-20T10:03:00+08:00', 'must never leak'],
        ['external-credit', 'credit', '1.00', 'PHP', 'settled', '2026-09-20T10:03:00+08:00', 'duplicate'],
    ]);
    $before = providerAttributionTableCounts();

    $exitCode = Artisan::call('x-change:treasury:audit-provider-attribution', [
        '--statement' => $path,
        '--connection' => 'netbank-primary',
        '--json' => true,
    ]);
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(Command::SUCCESS, $output)
        ->and($result['status'])->toBe('completed')
        ->and($result['read_only'])->toBeTrue()
        ->and($result['writes_database'])->toBeFalse()
        ->and($result['provider_calls'])->toBeFalse()
        ->and($result['moves_money'])->toBeFalse()
        ->and($result['safe_to_reconcile'])->toBeFalse()
        ->and($result['rows'])->toBe(5)
        ->and($result['counts'])->toMatchArray([
            'matched_inflows' => 1,
            'matched_outflows' => 1,
            'unmatched_provider_debits' => 1,
            'unmatched_provider_credits' => 1,
            'duplicates' => 1,
        ])
        ->and(providerAttributionTableCounts())->toBe($before)
        ->and($output)->not->toContain('credit-local')
        ->and($output)->not->toContain('external-debit')
        ->and($output)->not->toContain('private payer name')
        ->and($output)->not->toContain('private account number');
});

it('fails closed for malformed or unsupported statement input', function () {
    $path = tempnam(sys_get_temp_dir(), 'provider-statement-');
    file_put_contents($path, "transaction_id,direction,amount,currency,status\nsecret,sideways,1.00,USD,settled\n");

    $exitCode = Artisan::call('x-change:treasury:audit-provider-attribution', [
        '--statement' => $path,
        '--connection' => 'netbank-primary',
        '--json' => true,
    ]);
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($result['status'])->toBe('rejected')
        ->and($result['read_only'])->toBeTrue()
        ->and($result['message'])->toContain('missing required columns')
        ->and($output)->not->toContain('secret');
});

/** @param list<list<string>> $rows */
function providerStatementCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'provider-statement-');
    $handle = fopen($path, 'wb');
    fputcsv($handle, ['transaction_id', 'direction', 'amount', 'currency', 'status', 'occurred_at', 'description']);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

/** @return array<string, int> */
function providerAttributionTableCounts(): array
{
    return [
        'provider_funding_observations' => DB::table('provider_funding_observations')->count(),
        'disbursement_reconciliations' => DB::table('disbursement_reconciliations')->count(),
        'voucher_collections' => DB::table('voucher_collections')->count(),
        'treasury_inventory_operations' => DB::table('treasury_inventory_operations')->count(),
        'treasury_position_operations' => DB::table('treasury_position_operations')->count(),
    ];
}
