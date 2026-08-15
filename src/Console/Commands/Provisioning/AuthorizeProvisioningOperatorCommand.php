<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Provisioning;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Models\ProvisioningOperatorAuthorization;

final class AuthorizeProvisioningOperatorCommand extends Command
{
    protected $signature = 'x-change:provisioning:authorize-operator
        {operator : Stable operator identity value}
        {--column=mobile : Auth model identity column}
        {--capability=* : Capability to grant; repeat for more than one}
        {--authorization-reference= : Delegated authority or governance reference}
        {--valid-until= : Optional authorization expiry timestamp}';

    protected $description = 'Grant a named human operator explicit provisioning governance authority.';

    public function handle(SystemUserResolverContract $systemPrincipal): int
    {
        $modelClass = (string) config('auth.providers.users.model');
        $column = trim((string) $this->option('column'));
        $identity = trim((string) $this->argument('operator'));
        $authorizationReference = trim((string) $this->option('authorization-reference'));

        if (! is_subclass_of($modelClass, Model::class)
            || ! in_array($column, ['id', 'email', 'mobile'], true)
            || $identity === '' || $authorizationReference === '') {
            $this->error('A valid operator, identity column, and authorization reference are required.');

            return self::FAILURE;
        }

        /** @var Model|null $operator */
        $operator = $modelClass::query()->where($column, $identity)->first();
        $system = $systemPrincipal->resolve();

        if (! $operator instanceof Model) {
            $this->error('The named operator was not found.');

            return self::FAILURE;
        }

        if ($system instanceof Model && $operator->is($system)) {
            $this->error('The non-interactive System Principal cannot operate provisioning workflows.');

            return self::FAILURE;
        }

        $capabilities = array_values(array_unique(array_map('strval', (array) $this->option('capability'))));
        $capabilities = $capabilities === []
            ? [ProvisioningOperatorCapability::View->value]
            : $capabilities;
        $allowed = array_column(ProvisioningOperatorCapability::cases(), 'value');

        if (array_diff($capabilities, $allowed) !== []) {
            $this->error('Unknown provisioning operator capability requested.');

            return self::FAILURE;
        }

        $makerCapabilities = [
            ProvisioningOperatorCapability::Request->value,
            ProvisioningOperatorCapability::Issue->value,
        ];
        $checkerCapabilities = [
            ProvisioningOperatorCapability::Approve->value,
            ProvisioningOperatorCapability::Activate->value,
        ];

        if (array_intersect($capabilities, $makerCapabilities) !== []
            && array_intersect($capabilities, $checkerCapabilities) !== []) {
            $this->error('Provisioning maker and checker authority must belong to different operators.');

            return self::FAILURE;
        }

        $opposite = array_intersect($capabilities, $makerCapabilities) !== []
            ? $checkerCapabilities
            : (array_intersect($capabilities, $checkerCapabilities) !== [] ? $makerCapabilities : []);

        if ($opposite !== [] && ProvisioningOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->whereIn('capability', $opposite)
            ->currentlyValid()
            ->exists()) {
            $this->error('Provisioning maker and checker authority must belong to different operators.');

            return self::FAILURE;
        }

        $granter = $system instanceof Model ? $system : null;

        foreach ($capabilities as $capability) {
            ProvisioningOperatorAuthorization::query()->firstOrCreate([
                'operator_type' => $operator->getMorphClass(),
                'operator_id' => $operator->getKey(),
                'capability' => $capability,
                'authorization_reference' => $authorizationReference.':'.$capability,
            ], [
                'granted_by_type' => $granter?->getMorphClass(),
                'granted_by_id' => $granter?->getKey(),
                'valid_from' => now(),
                'valid_until' => filled($this->option('valid-until')) ? $this->option('valid-until') : null,
            ]);
        }

        $this->info('Provisioning operator authority granted.');

        foreach ($capabilities as $capability) {
            $this->line(' - '.$capability);
        }

        return self::SUCCESS;
    }
}
