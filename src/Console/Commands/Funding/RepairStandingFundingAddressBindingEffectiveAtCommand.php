<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\RepairStandingFundingAddressBindingEffectiveAt;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;

final class RepairStandingFundingAddressBindingEffectiveAtCommand extends Command
{
    protected $signature = 'x-change:funding:repair-standing-address-binding-effective-at
        {request : Activated binding migration request reference}
        {--operator= : Independent executor identity}
        {--column=id : Executor identity column}
        {--idempotency-key= : Stable caller-supplied repair key}
        {--authorization-reference= : Explicit repair authorization evidence}
        {--confirm-effective-time-correction : Confirm append-only normalization to approved evidence}
        {--json : Emit machine-readable output}';

    protected $description = 'Append an audited correction for the known binding cutover timezone defect.';

    public function handle(RepairStandingFundingAddressBindingEffectiveAt $repair): int
    {
        try {
            if (! (bool) $this->option('confirm-effective-time-correction')) {
                throw new \InvalidArgumentException('Explicit effective-time correction confirmation is required.');
            }

            $migration = StandingFundingAddressBindingMigration::query()
                ->where('reference', $this->argument('request'))
                ->firstOrFail();
            $operator = $this->operator();

            if (! $operator instanceof Model) {
                throw new \InvalidArgumentException('A valid correction operator is required.');
            }

            $correction = $repair->handle(
                $migration,
                $operator,
                (string) $this->option('idempotency-key'),
                (string) $this->option('authorization-reference'),
            );
            $payload = [
                'schema' => 'x-change.funding-standing-address-binding-effective-time-correction.v1',
                'reference' => $correction->reference,
                'binding_migration_reference' => $migration->reference,
                'binding_revision_reference' => $correction->bindingRevision()->value('reference'),
                'original_effective_at' => $correction->original_effective_at->toRfc3339String(),
                'corrected_effective_at' => $correction->corrected_effective_at->toRfc3339String(),
                'provider_calls' => false,
                'qr_regenerated' => false,
                'inventory_changed' => false,
            ];
            $this->line((bool) $this->option('json')
                ? (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
                : "Binding correction [{$correction->reference}] is active.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function operator(): ?Model
    {
        $model = (string) config('auth.providers.users.model');
        $column = trim((string) $this->option('column'));

        return is_subclass_of($model, Model::class)
            && in_array($column, ['id', 'email', 'mobile'], true)
            ? $model::query()->where($column, $this->option('operator'))->first()
            : null;
    }
}
