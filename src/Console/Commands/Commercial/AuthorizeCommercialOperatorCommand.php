<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceJournal;

final class AuthorizeCommercialOperatorCommand extends Command
{
    protected $signature = 'x-change:commercial:authorize-operator
        {operator? : Stable operator identity value}
        {--column=mobile : Auth model identity column}
        {--capability=* : Capability to grant; repeat for more than one}
        {--authorization-reference= : Delegated authority or governance reference}
        {--valid-until= : Optional authorization expiry timestamp}
        {--json : Output JSON}';

    protected $description = 'Grant a named operator explicit Commercial Control authority.';

    public function handle(
        SystemUserResolverContract $systemPrincipal,
        CommercialGovernanceJournal $journal,
    ): int {
        $modelClass = (string) config('auth.providers.users.model');
        $column = trim((string) $this->option('column'));
        $identity = trim((string) ($this->argument('operator') ?: $this->ask('Operator identity')));
        $authorizationReference = trim((string) ($this->option('authorization-reference')
            ?: $this->ask('Authorization reference')));

        if (! is_subclass_of($modelClass, Model::class)
            || $column === '' || $identity === '' || $authorizationReference === '') {
            $this->error('A valid auth model, identity column, operator, and authorization reference are required.');

            return self::FAILURE;
        }

        /** @var Model|null $operator */
        $operator = $modelClass::query()->where($column, $identity)->first();

        if (! $operator instanceof Model) {
            $this->error("Operator [{$column}={$identity}] was not found.");

            return self::FAILURE;
        }

        $capabilities = (array) $this->option('capability');
        $capabilities = $capabilities === []
            ? [CommercialOperatorCapability::ViewCommercialControls->value]
            : array_values(array_unique(array_map('strval', $capabilities)));
        $allowed = array_column(CommercialOperatorCapability::cases(), 'value');

        if (array_diff($capabilities, $allowed) !== []) {
            $this->error('Unknown Commercial Control capability requested.');

            return self::FAILURE;
        }

        $granter = $systemPrincipal->resolve();

        if ($operator->is($granter)) {
            $this->error('The non-interactive system principal cannot act as a Commercial maker or checker.');

            return self::FAILURE;
        }

        $separatedCapabilityPairs = [
            [
                CommercialOperatorCapability::ManageOfferings->value,
                CommercialOperatorCapability::ApproveOfferings->value,
            ],
            [
                CommercialOperatorCapability::RequestCommissionPayouts->value,
                CommercialOperatorCapability::ApproveCommissionPayouts->value,
            ],
        ];

        foreach ($separatedCapabilityPairs as $pair) {
            if (count(array_intersect($capabilities, $pair)) === 2) {
                $this->error('Commercial maker and checker authority must belong to different operators.');

                return self::FAILURE;
            }
        }

        $oppositeCapabilities = collect($separatedCapabilityPairs)
            ->flatMap(function (array $pair) use ($capabilities): array {
                if (in_array($pair[0], $capabilities, true)) {
                    return [$pair[1]];
                }

                if (in_array($pair[1], $capabilities, true)) {
                    return [$pair[0]];
                }

                return [];
            })
            ->all();

        if ($oppositeCapabilities !== []
            && CommercialOperatorAuthorization::query()
                ->where('operator_type', $operator->getMorphClass())
                ->where('operator_id', $operator->getKey())
                ->whereIn('capability', $oppositeCapabilities)
                ->currentlyValid()
                ->exists()) {
            $this->error('Commercial maker and checker authority must belong to different operators.');

            return self::FAILURE;
        }

        $authorizations = [];

        foreach ($capabilities as $capability) {
            $authorization = CommercialOperatorAuthorization::query()->firstOrCreate([
                'operator_type' => $operator->getMorphClass(),
                'operator_id' => $operator->getKey(),
                'capability' => $capability,
                'authorization_reference' => $authorizationReference,
            ], [
                'granted_by_type' => $granter instanceof Model ? $granter->getMorphClass() : null,
                'granted_by_id' => $granter instanceof Model ? $granter->getKey() : null,
                'valid_from' => now(),
                'valid_until' => filled($this->option('valid-until'))
                    ? $this->option('valid-until')
                    : null,
            ]);

            $authorizations[] = [
                'id' => $authorization->getKey(),
                'capability' => $capability,
                'created' => $authorization->wasRecentlyCreated,
            ];
            $journal->recordAuthorization($authorization);
        }

        $payload = [
            'schema' => 'x-change.commercial-operator-authorization.v1',
            'operator' => ['column' => $column, 'value' => $identity],
            'authorization_reference' => $authorizationReference,
            'authorizations' => $authorizations,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Commercial Control authority granted.');

            foreach ($authorizations as $authorization) {
                $this->line(' - '.$authorization['capability']);
            }
        }

        return self::SUCCESS;
    }
}
