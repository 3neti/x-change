<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Builder;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use SplFileObject;

final readonly class ProviderStatementAttributionAudit
{
    private const REQUIRED_COLUMNS = [
        'transaction_id',
        'direction',
        'amount',
        'currency',
        'status',
        'occurred_at',
    ];

    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private DatabaseManager $databases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(string $statementPath, string $connectionReference): array
    {
        $connection = $this->resolveConnection($connectionReference);
        $path = $this->validatedPath($statementPath);
        $database = $this->databases->connection();
        $schema = $database->getSchemaBuilder();
        $statement = new SplFileObject($path, 'rb');
        $statement->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        $header = $statement->fgetcsv();
        $columns = $this->headerColumns($header);
        $maxRows = max(1, (int) config('x-change.treasury.provider_attribution_audit.max_rows', 10_000));
        $detailLimit = max(0, (int) config('x-change.treasury.provider_attribution_audit.detail_limit', 100));
        $seen = [];
        $counts = array_fill_keys([
            'matched_inflows',
            'matched_outflows',
            'unmatched_provider_debits',
            'unmatched_provider_credits',
            'duplicates',
            'amount_mismatches',
            'status_mismatches',
        ], 0);
        $details = array_fill_keys(array_keys($counts), []);
        $totalRows = 0;

        while (! $statement->eof()) {
            $row = $statement->fgetcsv();

            if ($row === false || $row === [null]) {
                continue;
            }

            $totalRows++;

            if ($totalRows > $maxRows) {
                throw new InvalidArgumentException("The statement exceeds the configured {$maxRows}-row audit limit.");
            }

            $transaction = $this->normalizeRow(
                $columns,
                $row,
                $totalRows + 1,
                $connection->decimalPlaces,
                $connection->currency,
            );
            $transactionHash = hash('sha256', $connection->provider.'|'.$transaction['transaction_id']);

            if (isset($seen[$transaction['transaction_id']])) {
                $this->record($counts, $details, 'duplicates', $transactionHash, $detailLimit);

                continue;
            }

            $seen[$transaction['transaction_id']] = true;
            $evidence = $this->evidence($database, $schema, $connection->provider, $transaction['transaction_id']);

            if ($evidence === []) {
                $classification = $transaction['direction'] === 'debit'
                    ? 'unmatched_provider_debits'
                    : 'unmatched_provider_credits';
                $this->record($counts, $details, $classification, $transactionHash, $detailLimit);

                continue;
            }

            $directionalEvidence = collect($evidence)->filter(
                static fn (array $item): bool => $item['direction'] === $transaction['direction'],
            );
            $amountMatches = $directionalEvidence->contains(
                static fn (array $item): bool => $item['amount_minor'] === null
                    || $item['amount_minor'] === $transaction['amount_minor'],
            );
            $statusMatches = $directionalEvidence->contains(
                fn (array $item): bool => $this->statusesAgree($transaction['status'], $item['status']),
            );

            if ($directionalEvidence->isEmpty() || ! $amountMatches) {
                $this->record($counts, $details, 'amount_mismatches', $transactionHash, $detailLimit);

                continue;
            }

            if (! $statusMatches) {
                $this->record($counts, $details, 'status_mismatches', $transactionHash, $detailLimit);

                continue;
            }

            $classification = $transaction['direction'] === 'credit'
                ? 'matched_inflows'
                : 'matched_outflows';
            $this->record($counts, $details, $classification, $transactionHash, $detailLimit);
        }

        return [
            'schema_version' => 1,
            'status' => 'completed',
            'read_only' => true,
            'writes_database' => false,
            'provider_calls' => false,
            'moves_money' => false,
            'safe_to_reconcile' => false,
            'connection_reference' => $connection->reference,
            'provider' => $connection->provider,
            'currency' => $connection->currency,
            'statement_sha256' => hash_file('sha256', $path),
            'rows' => $totalRows,
            'counts' => $counts,
            'details' => $details,
        ];
    }

    private function resolveConnection(string $reference): TreasuryProviderConnectionData
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new InvalidArgumentException('An explicit Treasury connection is required.');
        }

        return $this->connections->active([$reference])[0];
    }

    private function validatedPath(string $path): string
    {
        $path = trim($path);
        $resolved = $path === '' ? false : realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved) || is_link($path)) {
            throw new InvalidArgumentException('The statement must be a readable regular file and may not be a symbolic link.');
        }

        $maxBytes = max(1, (int) config('x-change.treasury.provider_attribution_audit.max_bytes', 10 * 1024 * 1024));

        if (filesize($resolved) > $maxBytes) {
            throw new InvalidArgumentException('The statement exceeds the configured audit size limit.');
        }

        return $resolved;
    }

    /** @param array<int, string|null>|false $header @return array<string, int> */
    private function headerColumns(array|false $header): array
    {
        if ($header === false) {
            throw new InvalidArgumentException('The statement is empty.');
        }

        $columns = [];

        foreach ($header as $index => $column) {
            $name = mb_strtolower(trim((string) $column));

            if ($name !== '') {
                $columns[$name] = $index;
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($columns)));

        if ($missing !== []) {
            throw new InvalidArgumentException('The statement is missing required columns: '.implode(', ', $missing).'.');
        }

        return $columns;
    }

    /** @param array<string, int> $columns @param array<int, string|null> $row @return array{transaction_id:string,direction:string,amount_minor:int,currency:string,status:string} */
    private function normalizeRow(
        array $columns,
        array $row,
        int $line,
        int $decimalPlaces,
        string $expectedCurrency,
    ): array {
        $maxCellCharacters = max(1, (int) config('x-change.treasury.provider_attribution_audit.max_cell_characters', 2_048));

        if (count($row) > 64 || collect($row)->contains(
            static fn (mixed $cell): bool => mb_strlen((string) $cell) > $maxCellCharacters,
        )) {
            throw new InvalidArgumentException("Statement line {$line} exceeds the configured shape limits.");
        }

        $value = static fn (string $name): string => trim((string) ($row[$columns[$name]] ?? ''));
        $transactionId = $value('transaction_id');
        $direction = mb_strtolower($value('direction'));
        $currency = mb_strtoupper($value('currency'));
        $status = mb_strtolower($value('status'));

        if ($transactionId === '' || mb_strlen($transactionId) > 191) {
            throw new InvalidArgumentException("Statement line {$line} has an invalid transaction_id.");
        }

        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException("Statement line {$line} has an invalid direction.");
        }

        if ($currency !== $expectedCurrency || $status === '') {
            throw new InvalidArgumentException("Statement line {$line} has invalid currency or status data.");
        }

        try {
            new DateTimeImmutable($value('occurred_at'));
        } catch (\Exception) {
            throw new InvalidArgumentException("Statement line {$line} has an invalid occurred_at value.");
        }

        return [
            'transaction_id' => $transactionId,
            'direction' => $direction,
            'amount_minor' => $this->minorAmount($value('amount'), $decimalPlaces, $line),
            'currency' => $currency,
            'status' => $status,
        ];
    }

    private function minorAmount(string $amount, int $decimalPlaces, int $line): int
    {
        $pattern = $decimalPlaces === 0 ? '/^\d+$/' : '/^\d+(?:\.\d{1,'.$decimalPlaces.'})?$/';

        if (preg_match($pattern, $amount) !== 1) {
            throw new InvalidArgumentException("Statement line {$line} has an invalid amount.");
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $minor = $whole.str_pad($fraction, $decimalPlaces, '0');

        if (strlen($minor) > 18) {
            throw new InvalidArgumentException("Statement line {$line} amount is outside the supported range.");
        }

        return (int) $minor;
    }

    /** @return list<array{direction:string,amount_minor:?int,status:string}> */
    private function evidence(ConnectionInterface $database, Builder $schema, string $provider, string $transactionId): array
    {
        $evidence = [];

        if ($schema->hasTable('provider_funding_observations')) {
            $rows = $database->table('provider_funding_observations')
                ->where('provider_code', $provider)
                ->where('provider_transaction_id', $transactionId)
                ->get(['gross_amount_minor', 'provider_status']);
            foreach ($rows as $row) {
                $evidence[] = ['direction' => 'credit', 'amount_minor' => (int) $row->gross_amount_minor, 'status' => (string) $row->provider_status];
            }
        }

        if ($schema->hasTable('disbursement_reconciliations')) {
            $rows = $database->table('disbursement_reconciliations')
                ->where('provider', $provider)
                ->where(function ($query) use ($transactionId): void {
                    $query->where('provider_transaction_id', $transactionId)
                        ->orWhere('provider_reference', $transactionId);
                })
                ->get(['amount', 'status']);
            foreach ($rows as $row) {
                $evidence[] = ['direction' => 'debit', 'amount_minor' => $this->decimalDatabaseAmountToMinor($row->amount), 'status' => (string) $row->status];
            }
        }

        if ($schema->hasTable('voucher_collections')) {
            $rows = $database->table('voucher_collections')
                ->where('provider', $provider)
                ->where(function ($query) use ($transactionId): void {
                    $query->where('provider_transaction_id', $transactionId)
                        ->orWhere('provider_reference', $transactionId);
                })
                ->get(['collected_amount_minor', 'status']);
            foreach ($rows as $row) {
                $evidence[] = ['direction' => 'credit', 'amount_minor' => (int) $row->collected_amount_minor, 'status' => (string) $row->status];
            }
        }

        foreach (['treasury_inventory_operations', 'treasury_position_operations'] as $table) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            $rows = $database->table($table)
                ->where(function ($query) use ($provider, $transactionId): void {
                    $query->where('external_reference', $transactionId)
                        ->orWhere('external_reference', $provider.':'.$transactionId);
                })
                ->get(['amount_minor', 'status', 'operation_type']);
            foreach ($rows as $row) {
                $type = mb_strtolower((string) $row->operation_type);
                $direction = str_contains($type, 'disbursement') || str_contains($type, 'derecognition') ? 'debit' : 'credit';
                $evidence[] = ['direction' => $direction, 'amount_minor' => (int) $row->amount_minor, 'status' => (string) $row->status];
            }
        }

        return $evidence;
    }

    private function decimalDatabaseAmountToMinor(mixed $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        return $this->minorAmount((string) $amount, 2, 0);
    }

    private function statusesAgree(string $statement, string $local): bool
    {
        $settled = ['settled', 'succeeded', 'success', 'completed', 'collected', 'committed', 'paid'];

        return $statement === $local
            || (in_array($statement, $settled, true) && in_array(mb_strtolower($local), $settled, true));
    }

    /** @param array<string, int> $counts @param array<string, list<string>> $details */
    private function record(array &$counts, array &$details, string $classification, string $hash, int $limit): void
    {
        $counts[$classification]++;

        if (count($details[$classification]) < $limit) {
            $details[$classification][] = $hash;
        }
    }
}
