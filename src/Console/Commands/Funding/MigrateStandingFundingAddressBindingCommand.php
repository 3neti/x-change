<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\InspectStandingFundingAddressBindingMigration;
use LBHurtado\XChange\Actions\Funding\RequestStandingFundingAddressBindingMigration;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final class MigrateStandingFundingAddressBindingCommand extends Command
{
    protected $signature = 'x-change:funding:migrate-standing-address-binding
        {--address= : Standing Funding Address reference}
        {--to-current-client-funds : Resolve the owner current Client Funds binding}
        {--effective-at= : Proposed ISO-8601 cutover time; defaults to one hour ahead}
        {--operator= : Viewing or requesting operator identity}
        {--column=id : Operator identity column}
        {--idempotency-key= : Stable caller-supplied request key}
        {--commit : Submit the migration for independent approval}
        {--json : Emit machine-readable output}';

    protected $description = 'Preview or request an append-only Standing Funding Address binding migration.';

    public function handle(
        InspectStandingFundingAddressBindingMigration $inspect,
        RequestStandingFundingAddressBindingMigration $request,
        TreasuryOperatorAuthority $authority,
    ): int {
        $reference = trim((string) $this->option('address'));
        $address = StandingFundingAddress::query()->where('reference', $reference)->first();

        if (! $address instanceof StandingFundingAddress
            || ! (bool) $this->option('to-current-client-funds')) {
            return $this->emitFailure('A valid address and --to-current-client-funds are required.');
        }

        $operator = $this->operator();

        if (! $operator instanceof Model) {
            return $this->emitFailure('A valid Treasury operator is required.');
        }

        try {
            $authority->assertAllows($operator, TreasuryOperatorCapability::ViewFundingBindings);
            $effectiveAt = filled($this->option('effective-at'))
                ? CarbonImmutable::parse((string) $this->option('effective-at'))
                : now()->addHour()->toImmutable();
            $preview = $inspect->handle($address, $effectiveAt);

            if (! (bool) $this->option('commit')) {
                return $this->emit([
                    'schema' => 'x-change.funding-standing-address-binding-migration-preview.v1',
                    'mode' => 'dry_run',
                    'safe' => $preview['safe'],
                    'evidence' => $preview['evidence'],
                    'evidence_hash' => $preview['evidence_hash'],
                    'writes_performed' => false,
                ], $preview['safe'] === true ? self::SUCCESS : self::FAILURE);
            }

            $idempotencyKey = trim((string) $this->option('idempotency-key'));

            if ($idempotencyKey === '') {
                return $this->emitFailure('--idempotency-key is required with --commit.');
            }

            $migration = $request->handle($address, $operator, $idempotencyKey, $effectiveAt);

            return $this->emit([
                'schema' => 'x-change.funding-standing-address-binding-migration-request.v1',
                'mode' => 'request',
                'reference' => $migration->reference,
                'status' => $migration->status->value,
                'evidence_hash' => $migration->evidence_hash,
                'provider_calls' => false,
                'inventory_changed' => false,
            ]);
        } catch (\Throwable $exception) {
            return $this->emitFailure($exception->getMessage());
        }
    }

    private function operator(): ?Model
    {
        $model = (string) config('auth.providers.users.model');
        $column = trim((string) $this->option('column'));

        if (! is_subclass_of($model, Model::class)
            || ! in_array($column, ['id', 'email', 'mobile'], true)) {
            return null;
        }

        return $model::query()->where($column, $this->option('operator'))->first();
    }

    /** @param array<string, mixed> $payload */
    private function emit(array $payload, int $exitCode = self::SUCCESS): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Field', 'Value'], collect($payload)
                ->reject(fn (mixed $value): bool => is_array($value))
                ->map(fn (mixed $value, string $key): array => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value])
                ->values()
                ->all());
        }

        return $exitCode;
    }

    private function emitFailure(string $message): int
    {
        return $this->emit([
            'schema' => 'x-change.funding-standing-address-binding-migration-error.v1',
            'message' => $message,
        ], self::FAILURE);
    }
}
