<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\ActivateStandingFundingAddressBindingMigration;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;

final class ActivateStandingFundingAddressBindingMigrationCommand extends Command
{
    protected $signature = 'x-change:funding:activate-standing-address-binding
        {request : Approved binding migration request reference}
        {--operator= : Executor operator identity}
        {--column=id : Executor identity column}
        {--json : Emit machine-readable output}';

    protected $description = 'Activate an approved append-only Standing Funding Address binding revision.';

    public function handle(ActivateStandingFundingAddressBindingMigration $activate): int
    {
        try {
            $migration = StandingFundingAddressBindingMigration::query()
                ->where('reference', $this->argument('request'))
                ->firstOrFail();
            $operator = $this->operator();

            if (! $operator instanceof Model) {
                throw new \InvalidArgumentException('A valid activation operator is required.');
            }

            $migration = $activate->handle($migration, $operator);
            $payload = [
                'schema' => 'x-change.funding-standing-address-binding-migration-activation.v1',
                'reference' => $migration->reference,
                'status' => $migration->status->value,
                'binding_revision_reference' => $migration->activatedBindingRevision?->reference,
                'effective_at' => data_get($migration->evidence_snapshot, 'proposed_effective_at'),
                'provider_calls' => false,
                'qr_regenerated' => false,
                'inventory_changed' => false,
            ];
            $this->line((bool) $this->option('json')
                ? (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
                : "Binding migration [{$migration->reference}] is {$migration->status->value}.");

            return $migration->status->value === 'activated' ? self::SUCCESS : self::FAILURE;
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
