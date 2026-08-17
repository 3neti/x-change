<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\ApproveStandingFundingAddressBindingMigration;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;

final class ApproveStandingFundingAddressBindingMigrationCommand extends Command
{
    protected $signature = 'x-change:funding:approve-standing-address-binding
        {request : Binding migration request reference}
        {--operator= : Checker operator identity}
        {--column=id : Checker identity column}
        {--approval-reference= : Independent approval evidence reference}
        {--json : Emit machine-readable output}';

    protected $description = 'Independently approve a Standing Funding Address binding migration.';

    public function handle(ApproveStandingFundingAddressBindingMigration $approve): int
    {
        try {
            $migration = StandingFundingAddressBindingMigration::query()
                ->where('reference', $this->argument('request'))
                ->firstOrFail();
            $operator = $this->operator();

            if (! $operator instanceof Model) {
                throw new \InvalidArgumentException('A valid checker operator is required.');
            }

            $migration = $approve->handle(
                $migration,
                $operator,
                (string) $this->option('approval-reference'),
            );
            $payload = [
                'schema' => 'x-change.funding-standing-address-binding-migration-approval.v1',
                'reference' => $migration->reference,
                'status' => $migration->status->value,
                'evidence_hash' => $migration->evidence_hash,
                'provider_calls' => false,
                'inventory_changed' => false,
            ];
            $this->line((bool) $this->option('json')
                ? (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
                : "Binding migration [{$migration->reference}] is {$migration->status->value}.");

            return $migration->status->value === 'approved' ? self::SUCCESS : self::FAILURE;
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
