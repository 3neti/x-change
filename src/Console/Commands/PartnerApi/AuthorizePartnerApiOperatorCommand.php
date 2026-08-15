<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\PartnerApi;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;

final class AuthorizePartnerApiOperatorCommand extends Command
{
    protected $signature = 'x-change:partner-api:authorize-operator
        {operator : Stable operator identity value}
        {--column=mobile : Auth model identity column}
        {--capability=* : Capability to grant; repeat for more than one}
        {--authorization-reference= : Delegated authority or governance reference}
        {--valid-until= : Optional authorization expiry timestamp}';

    protected $description = 'Grant a named human operator explicit Partner API administration authority.';

    public function handle(
        SystemUserResolverContract $systemPrincipal,
        PartnerApiGovernanceJournal $journal,
    ): int {
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

        if (! $operator instanceof Model) {
            $this->error('The named operator was not found.');

            return self::FAILURE;
        }

        if ($operator->is($systemPrincipal->resolve())) {
            $this->error('The non-interactive System Principal cannot operate the API Partners workspace.');

            return self::FAILURE;
        }

        $capabilities = array_values(array_unique(array_map('strval', (array) $this->option('capability'))));
        $capabilities = $capabilities === []
            ? [PartnerApiOperatorCapability::ViewClients->value]
            : $capabilities;
        $allowed = array_column(PartnerApiOperatorCapability::cases(), 'value');

        if (array_diff($capabilities, $allowed) !== []) {
            $this->error('Unknown Partner API operator capability requested.');

            return self::FAILURE;
        }

        $granter = $systemPrincipal->resolve();

        foreach ($capabilities as $capability) {
            $authorization = PartnerApiOperatorAuthorization::query()->firstOrCreate([
                'operator_type' => $operator->getMorphClass(),
                'operator_id' => $operator->getKey(),
                'capability' => $capability,
                'authorization_reference' => $authorizationReference,
            ], [
                'granted_by_type' => $granter instanceof Model ? $granter->getMorphClass() : null,
                'granted_by_id' => $granter instanceof Model ? $granter->getKey() : null,
                'valid_from' => now(),
                'valid_until' => filled($this->option('valid-until')) ? $this->option('valid-until') : null,
            ]);
            $journal->recordAuthorization($authorization);
        }

        $this->info('Partner API operator authority granted.');

        foreach ($capabilities as $capability) {
            $this->line(' - '.$capability);
        }

        return self::SUCCESS;
    }
}
