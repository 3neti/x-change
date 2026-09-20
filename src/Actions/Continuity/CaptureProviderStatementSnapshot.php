<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Continuity;

use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\DeploymentRuntimeTier;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;
use Throwable;

final readonly class CaptureProviderStatementSnapshot
{
    private const CSV_HEADER = [
        'transaction_id',
        'direction',
        'amount',
        'currency',
        'status',
        'occurred_at',
    ];

    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private NetbankFundingApiClient $netbank,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $connectionReference,
        string $from,
        string $to,
        int $maximumRows,
        bool $sensitiveCaptureConfirmed,
    ): array {
        if (! $sensitiveCaptureConfirmed) {
            throw new InvalidArgumentException('Explicit confirmation is required before capturing private provider statement evidence.');
        }

        $connection = $this->resolveConnection($connectionReference);
        $startDate = $this->date($from, 'from');
        $endDate = $this->date($to, 'to');
        $this->assertBounds($startDate, $endDate, $maximumRows);
        $accountNumber = trim((string) config('payment-gateway.netbank.funding.corporate_account_number'));

        if ($accountNumber === '') {
            throw new InvalidArgumentException('The configured NetBank corporate account is unavailable.');
        }

        [$diskName, $directory, $disk] = $this->privateDisk();
        $stream = tmpfile();

        if (! is_resource($stream)) {
            throw new RuntimeException('Private statement staging is unavailable.');
        }

        try {
            fputcsv($stream, self::CSV_HEADER);
            $rows = 0;

            foreach ($this->netbank->accountTransactions($accountNumber, $startDate, $endDate, $maximumRows) as $transaction) {
                fputcsv($stream, $this->normalize($transaction, $connection));
                $rows++;

                if (ftell($stream) > $this->maximumBytes()) {
                    throw new InvalidArgumentException('The provider statement snapshot exceeds the configured size limit.');
                }
            }

            rewind($stream);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            $sha256 = hash_final($hash);
            $bytes = ftell($stream);
            rewind($stream);

            $prefix = sprintf(
                '%s/%s/%s_%s',
                $directory,
                $connection->reference,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            );
            $statementPath = "{$prefix}/{$sha256}.csv";
            $manifestPath = "{$prefix}/{$sha256}.manifest.json";
            $manifest = [
                'schema' => 'x-change.provider-statement-snapshot.v1',
                'status' => 'captured_bounded',
                'connection_reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
                'end_date_exclusive' => true,
                'row_count' => $rows,
                'row_limit' => $maximumRows,
                'row_limit_reached' => $rows === $maximumRows,
                'statement_sha256' => $sha256,
                'statement_bytes' => $bytes,
                'statement_path' => $statementPath,
                'created_at' => now('UTC')->toIso8601String(),
                'read_only' => true,
                'writes_database' => false,
                'writes_journal' => false,
                'moves_money' => false,
                'safe_to_reconcile' => false,
            ];
            $reused = $this->publish($disk, $statementPath, $manifestPath, $stream, $manifest);

            return [
                'schema' => 'x-change.provider-statement-capture.v1',
                'status' => 'captured_bounded',
                'connection_reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
                'end_date_exclusive' => true,
                'row_count' => $rows,
                'row_limit' => $maximumRows,
                'row_limit_reached' => $rows === $maximumRows,
                'artifact' => [
                    'disk' => $diskName,
                    'statement_path' => $statementPath,
                    'manifest_path' => $manifestPath,
                    'sha256' => $sha256,
                    'bytes' => $bytes,
                    'reused' => $reused,
                ],
                'provider_calls' => true,
                'writes_storage' => true,
                'writes_database' => false,
                'writes_journal' => false,
                'moves_money' => false,
                'safe_to_reconcile' => false,
            ];
        } finally {
            fclose($stream);
        }
    }

    private function resolveConnection(string $reference): TreasuryProviderConnectionData
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new InvalidArgumentException('An explicit Treasury connection is required.');
        }

        $connection = $this->connections->active([$reference])[0];

        if ($connection->provider !== 'netbank') {
            throw new InvalidArgumentException('Provider statement capture currently supports NetBank connections only.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/', $connection->reference) !== 1) {
            throw new InvalidArgumentException('The Treasury connection reference is not safe for private evidence storage.');
        }

        return $connection;
    }

    private function date(string $value, string $name): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== trim($value)) {
            throw new InvalidArgumentException("The {$name} date must use YYYY-MM-DD.");
        }

        return $date;
    }

    private function assertBounds(DateTimeImmutable $from, DateTimeImmutable $to, int $maximumRows): void
    {
        if ($to <= $from) {
            throw new InvalidArgumentException('The provider statement end date must be after the start date.');
        }

        $maximumRangeDays = max(1, (int) config('x-change.continuity.provider_statement.max_range_days', 3660));

        if ($from->diff($to)->days > $maximumRangeDays) {
            throw new InvalidArgumentException('The provider statement date range exceeds the configured limit.');
        }

        $configuredMaximumRows = max(1, (int) config('x-change.continuity.provider_statement.max_rows', 10_000));

        if ($maximumRows < 1 || $maximumRows > $configuredMaximumRows) {
            throw new InvalidArgumentException('The provider statement row limit is invalid.');
        }
    }

    /** @return array{string, string, FilesystemAdapter} */
    private function privateDisk(): array
    {
        $diskName = trim((string) config('x-change.continuity.provider_statement.disk', 'local'));
        $directory = trim((string) config('x-change.continuity.provider_statement.directory', 'x-change/provider-statements'), '/');

        if ($diskName === ''
            || $diskName === 'public'
            || $directory === ''
            || str_contains('/'.$directory.'/', '/../')
            || preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $directory) !== 1) {
            throw new InvalidArgumentException('A private provider statement disk and directory are required.');
        }

        $driver = (string) config("filesystems.disks.{$diskName}.driver");
        $runtimeTier = DeploymentRuntimeTier::resolve((string) config(
            'x-change.deployment.runtime_tier',
            DeploymentRuntimeTier::Local->value,
        ));

        if (! app()->environment('testing') && $driver === '') {
            throw new InvalidArgumentException('The private provider statement disk is not configured.');
        }

        if ($runtimeTier->requiresDurableInfrastructure() && ($diskName === 'local' || $driver === 'local')) {
            throw new InvalidArgumentException('Staging and production provider statements require a durable non-local private disk.');
        }

        return [$diskName, $directory, Storage::disk($diskName)];
    }

    /** @param array<string, mixed> $transaction
     * @return list<string>
     */
    private function normalize(array $transaction, TreasuryProviderConnectionData $connection): array
    {
        $transactionId = trim((string) ($transaction['transaction_id'] ?? ''));
        $direction = mb_strtolower(trim((string) ($transaction['type'] ?? '')));
        $currency = mb_strtoupper(trim((string) data_get($transaction, 'amount.cur', '')));
        $amountMinor = trim((string) data_get($transaction, 'amount.num', ''));
        $status = mb_strtolower(trim((string) ($transaction['status'] ?? '')));
        $occurredAt = trim((string) ($transaction['date'] ?? ''));

        if ($transactionId === '' || mb_strlen($transactionId) > 191 || preg_match('/[\x00-\x1F\x7F]/u', $transactionId) === 1) {
            throw new InvalidArgumentException('The provider returned an invalid transaction identifier.');
        }

        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException('The provider returned an invalid transaction direction.');
        }

        if ($currency !== $connection->currency || preg_match('/^\d{1,24}$/', $amountMinor) !== 1) {
            throw new InvalidArgumentException('The provider returned invalid transaction amount data.');
        }

        if ($status === '' || mb_strlen($status) > 64 || preg_match('/[\x00-\x1F\x7F]/u', $status) === 1) {
            throw new InvalidArgumentException('The provider returned an invalid transaction status.');
        }

        try {
            $date = new DateTimeImmutable($occurredAt);
        } catch (Throwable) {
            throw new InvalidArgumentException('The provider returned an invalid transaction timestamp.');
        }

        return [
            $transactionId,
            $direction,
            $this->decimalAmount($amountMinor, $connection->decimalPlaces),
            $currency,
            $status,
            $date->format(DATE_ATOM),
        ];
    }

    private function decimalAmount(string $minor, int $decimalPlaces): string
    {
        if ($decimalPlaces === 0) {
            return ltrim($minor, '0') ?: '0';
        }

        $minor = str_pad($minor, $decimalPlaces + 1, '0', STR_PAD_LEFT);
        $whole = substr($minor, 0, -$decimalPlaces);
        $fraction = substr($minor, -$decimalPlaces);

        return (ltrim($whole, '0') ?: '0').'.'.$fraction;
    }

    private function maximumBytes(): int
    {
        return max(1, (int) config('x-change.continuity.provider_statement.max_bytes', 10 * 1024 * 1024));
    }

    /** @param array<string, mixed> $manifest */
    private function publish(
        FilesystemAdapter $disk,
        string $statementPath,
        string $manifestPath,
        mixed $stream,
        array $manifest,
    ): bool {
        if ($disk->exists($statementPath) && $disk->exists($manifestPath)) {
            $existing = json_decode((string) $disk->get($manifestPath), true);

            if (is_array($existing) && hash_equals((string) ($existing['statement_sha256'] ?? ''), (string) $manifest['statement_sha256'])) {
                return true;
            }

            throw new RuntimeException('The provider statement artifact conflicts with existing private evidence.');
        }

        try {
            if (! $disk->put($statementPath, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('The provider statement artifact could not be stored privately.');
            }

            if (! $disk->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ['visibility' => 'private'])) {
                throw new RuntimeException('The provider statement manifest could not be stored privately.');
            }
        } catch (Throwable $exception) {
            $disk->delete([$statementPath, $manifestPath]);
            throw $exception;
        }

        return false;
    }
}
