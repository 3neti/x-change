<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Cockpit\CockpitAccountReadModelProvider;
use Throwable;

final class AccountManagementScenarioRunner implements ScenarioRunnerContract
{
    /**
     * @var array<string, mixed>
     */
    private const SCENARIO_CONFIG = [
        'payment-gateway.netbank.funding.corporate_account_number' => '700000001111',
        'payment-gateway.netbank.funding.corporate_account_name' => 'Scenario Shared Treasury',
        'payment-gateway.netbank.funding.vca_alias' => '91001',
        'payment-gateway.netbank.funding.vca_alias_token' => 'scenario-shared-token',
        'constellation.funding.wallet_id' => 'SCENARIO-SHARED-WALLET-001111',
        'x-change.funding.providers.netbank.enabled' => true,
        'x-change.funding.providers.paynamics_constellation.enabled' => true,
    ];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly FundingDestinationResolverContract $destinations,
        private readonly CockpitAccountReadModelProvider $accounts,
        private readonly CreateFundingIntent $createFundingIntent,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! (bool) config('x-change.cockpit.account_scenario.enabled', false)) {
            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: $this->basePayload($context, [
                    'success' => false,
                    'message' => 'The account-management lifecycle scenario is disabled.',
                    'steps' => [],
                ]),
            );
        }

        $connection = $this->databases->connection();
        $startingTransactionLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($context->issuer);
        $originalConfig = $this->applyScenarioConfig();
        $payload = [];
        $exitCode = Command::SUCCESS;

        $connection->beginTransaction();

        try {
            $payload = $this->executeScenario($context);
        } catch (Throwable) {
            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'The account-management lifecycle scenario could not complete safely.',
                'steps' => [],
            ];
        } finally {
            while ($connection->transactionLevel() > $startingTransactionLevel) {
                $connection->rollBack();
            }

            $this->restoreConfig($originalConfig);
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingTransactionLevel
            && hash_equals($startingState, $this->stateDigest($context->issuer));

        if (! $rollbackCompleted) {
            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'The account-management lifecycle scenario could not confirm rollback.',
                'steps' => [],
            ];
        }

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: $this->basePayload($context, [
                ...$payload,
                'rollback_completed' => $rollbackCompleted,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function executeScenario(ScenarioRunContext $context): array
    {
        $owner = $context->issuer;
        $accountReference = 'wallet:scenario-'.Str::lower((string) Str::ulid());
        $steps = [];

        $this->selectShared($owner, 'netbank');
        $this->selectShared($owner, 'paynamics_constellation');
        $sharedReadModel = $this->accounts->forOwner($owner, $accountReference);
        $steps[] = $this->step(
            key: 'shared_defaults',
            label: 'Shared destinations are the safe default',
            outcome: 'ready',
            summary: 'Both providers resolve to platform-managed treasury destinations until a dedicated destination is explicitly selected.',
            providers: $this->providerStates($sharedReadModel),
            facts: [
                $this->fact('Fallback policy', 'Explicit selection only'),
                $this->fact('Balance impact', 'None'),
            ],
        );

        $netbankLink = $this->createNetbankLink($owner);
        $this->selectDedicated($owner, 'netbank', $netbankLink);
        $netbankDestination = $this->destinations->resolve($owner, 'netbank', $accountReference);
        $netbankReadModel = $this->accounts->forOwner($owner, $accountReference);
        $steps[] = $this->step(
            key: 'netbank_dedicated_ready',
            label: 'NetBank dedicated routing becomes eligible',
            outcome: 'ready',
            summary: 'An imported write-only VCA credential activates a dedicated destination without exposing the account number or token.',
            providers: [$this->providerState($netbankReadModel, 'netbank')],
            facts: [
                $this->fact('Destination', $netbankDestination->displayReference),
                $this->fact('Verification', 'Credential supplied'),
            ],
        );

        $intent = $this->createFundingIntent->handle(new CreateFundingIntentData(
            accountReference: $accountReference,
            provider: 'netbank',
            expectedAmountMinor: 2_500,
            currency: 'PHP',
            idempotencyKey: 'scenario-'.Str::lower((string) Str::ulid()),
            actorType: $owner::class,
            actorId: (string) $owner->getKey(),
            metadata: [
                'source' => 'account-management-lifecycle-scenario',
                'rollback_only' => true,
            ],
            destination: $netbankDestination,
        ));
        $snapshotBeforeRotation = (array) $intent->destination_snapshot_ciphertext;
        $steps[] = $this->step(
            key: 'netbank_intent_snapshot',
            label: 'Funding Intent locks the selected destination',
            outcome: 'protected',
            summary: 'The intent stores an encrypted destination snapshot so later account changes cannot redirect an existing transfer.',
            providers: [$this->providerState($netbankReadModel, 'netbank')],
            facts: [
                $this->fact('Intent amount', '₱25.00'),
                $this->fact('Snapshot destination', (string) data_get($snapshotBeforeRotation, 'displayReference')),
                $this->fact('Instructions issued', 'No'),
            ],
        );

        $routingBeforeRotation = (array) $netbankLink->routing_profile_ciphertext;
        $netbankLink->forceFill([
            'routing_profile_ciphertext' => [
                ...$routingBeforeRotation,
                'vca_alias_token' => 'scenario-rotated-write-only-token',
            ],
            'verification_status' => 'verified',
            'verified_at' => now(),
            'last_synced_at' => now(),
        ])->save();
        $netbankLink->refresh();
        $intent->refresh();
        $snapshotAfterRotation = (array) $intent->destination_snapshot_ciphertext;
        $steps[] = $this->step(
            key: 'netbank_token_rotation',
            label: 'Token rotation is a separate warned operation',
            outcome: 'protected',
            summary: 'Rotation replaces only the write-only credential. The destination identity and existing Funding Intent snapshot remain unchanged.',
            providers: [$this->providerState(
                $this->accounts->forOwner($owner, $accountReference),
                'netbank',
            )],
            facts: [
                $this->fact('Credential changed', 'Yes'),
                $this->fact('Destination identity changed', 'No'),
                $this->fact(
                    'Existing intent changed',
                    $snapshotBeforeRotation === $snapshotAfterRotation ? 'No' : 'Unexpectedly',
                ),
            ],
        );

        $paynamicsLink = $this->createPaynamicsLink($owner);
        $this->selectDedicated($owner, 'paynamics_constellation', $paynamicsLink);
        $paynamicsBlocked = false;

        try {
            $this->destinations->resolve($owner, 'paynamics_constellation', $accountReference);
        } catch (FundingDestinationUnavailable) {
            $paynamicsBlocked = true;
        }

        $paynamicsReadModel = $this->accounts->forOwner($owner, $accountReference);
        $steps[] = $this->step(
            key: 'paynamics_reachable_blocked',
            label: 'Reachability does not prove Paynamics ownership',
            outcome: $paynamicsBlocked ? 'blocked' : 'failed',
            summary: 'The wallet can be found, but dedicated Funding remains fail-closed until authoritative ownership evidence arrives.',
            providers: [$this->providerState($paynamicsReadModel, 'paynamics_constellation')],
            facts: [
                $this->fact('Wallet reachable', 'Yes'),
                $this->fact('Ownership verified', 'No'),
                $this->fact('Funding eligible', $paynamicsBlocked ? 'No' : 'Unexpectedly'),
            ],
        );

        $paynamicsLink->forceFill([
            'verification_status' => 'ownership_verified',
            'identity_level' => 'provider_verified_wallet_owner',
            'verified_at' => now(),
            'activated_at' => now(),
            'last_synced_at' => now(),
        ])->save();
        $paynamicsDestination = $this->destinations->resolve(
            $owner,
            'paynamics_constellation',
            $accountReference,
        );
        $verifiedPaynamicsReadModel = $this->accounts->forOwner($owner, $accountReference);
        $steps[] = $this->step(
            key: 'paynamics_ownership_verified',
            label: 'Authoritative ownership evidence unlocks eligibility',
            outcome: 'ready',
            summary: 'Only the ownership-verified state allows the dedicated wallet to resolve as a Funding destination.',
            providers: [$this->providerState($verifiedPaynamicsReadModel, 'paynamics_constellation')],
            facts: [
                $this->fact('Destination', $paynamicsDestination->displayReference),
                $this->fact('Ownership evidence', 'Synthetic scenario evidence'),
                $this->fact('Balance impact', 'None'),
            ],
        );

        $this->selectShared($owner, 'netbank');
        $this->selectShared($owner, 'paynamics_constellation');
        $returnedReadModel = $this->accounts->forOwner($owner, $accountReference);
        $steps[] = $this->step(
            key: 'shared_restored_history_retained',
            label: 'Returning to shared keeps connection history',
            outcome: 'complete',
            summary: 'Future intents return to shared treasury routing while prior dedicated connections remain visible as non-active history.',
            providers: $this->providerStates($returnedReadModel),
            facts: [
                $this->fact('Active mode', 'Shared'),
                $this->fact('Scenario connections retained', '2'),
                $this->fact('Durable records after run', '0'),
            ],
        );

        return [
            'success' => $paynamicsBlocked
                && $snapshotBeforeRotation === $snapshotAfterRotation,
            'message' => 'Rollback-only account-management lifecycle completed.',
            'steps' => $steps,
        ];
    }

    private function createNetbankLink(Model $owner): ProviderAccountLink
    {
        return ProviderAccountLink::query()->create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'provider' => 'netbank',
            'topology' => 'dedicated',
            'purpose' => 'funding',
            'mode' => 'bank_account_link',
            'status' => 'ready',
            'verification_status' => 'credential_supplied',
            'identity_level' => 'corporate_account_vca',
            'capabilities' => ['funding', 'vca'],
            'metadata' => [
                'scenario' => true,
                'enrollment' => 'import',
            ],
            'routing_profile_ciphertext' => [
                'bank_account_number' => '991100004242',
                'bank_account_name' => 'Scenario Dedicated Treasury',
                'vca_alias' => '54321',
                'vca_alias_token' => 'scenario-write-only-netbank-token',
            ],
            'routing_fingerprint' => hash('sha256', 'netbank|991100004242|54321'),
            'display_reference' => '•••• 4242 · VCA 54321',
            'ready_at' => now(),
            'activated_at' => now(),
            'last_synced_at' => now(),
        ]);
    }

    private function createPaynamicsLink(Model $owner): ProviderAccountLink
    {
        return ProviderAccountLink::query()->create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'provider' => 'paynamics_constellation',
            'topology' => 'dedicated',
            'purpose' => 'funding',
            'mode' => 'wallet_resolve',
            'status' => 'ready',
            'verification_status' => 'reachable',
            'identity_level' => 'wallet_exists_only',
            'capabilities' => ['balance_check', 'funding'],
            'metadata' => [
                'scenario' => true,
                'ownership_verification_required' => true,
            ],
            'provider_wallet_id' => 'SCENARIO-DEMO-WALLET-654321',
            'routing_fingerprint' => hash('sha256', 'paynamics_constellation|SCENARIO-DEMO-WALLET-654321'),
            'display_reference' => '•••• 654321',
            'ready_at' => now(),
            'last_synced_at' => now(),
        ]);
    }

    private function selectShared(Model $owner, string $provider): void
    {
        $preference = FundingDestinationPreference::query()->firstOrNew([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'provider_code' => $provider,
        ]);
        $preference->forceFill([
            'mode' => 'shared',
            'provider_account_link_id' => null,
            'version' => $preference->exists ? $preference->version + 1 : 1,
            'changed_by_type' => $owner::class,
            'changed_by_id' => (string) $owner->getKey(),
        ])->save();
    }

    private function selectDedicated(
        Model $owner,
        string $provider,
        ProviderAccountLink $link,
    ): void {
        $preference = FundingDestinationPreference::query()->firstOrNew([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'provider_code' => $provider,
        ]);
        $preference->forceFill([
            'mode' => 'dedicated',
            'provider_account_link_id' => $link->getKey(),
            'version' => $preference->exists ? $preference->version + 1 : 1,
            'changed_by_type' => $owner::class,
            'changed_by_id' => (string) $owner->getKey(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $readModel
     * @return array<int, array<string, mixed>>
     */
    private function providerStates(array $readModel): array
    {
        return [
            $this->providerState($readModel, 'netbank'),
            $this->providerState($readModel, 'paynamics_constellation'),
        ];
    }

    /**
     * @param  array<string, mixed>  $readModel
     * @return array<string, mixed>
     */
    private function providerState(array $readModel, string $provider): array
    {
        $state = collect((array) data_get($readModel, 'providers'))
            ->first(fn (mixed $candidate): bool => data_get($candidate, 'code') === $provider);

        return [
            'code' => $provider,
            'label' => (string) data_get($state, 'label', $provider),
            'mode' => (string) data_get($state, 'mode', 'unavailable'),
            'shared' => [
                'status' => (string) data_get($state, 'shared.status', 'unavailable'),
                'display_reference' => data_get($state, 'shared.display_reference'),
            ],
            'dedicated' => [
                'configured' => (bool) data_get($state, 'dedicated.configured', false),
                'display_reference' => data_get($state, 'dedicated.display_reference'),
                'status' => (string) data_get($state, 'dedicated.status', 'unavailable'),
                'verification_status' => (string) data_get(
                    $state,
                    'dedicated.verification_status',
                    'unavailable',
                ),
                'can_activate' => (bool) data_get($state, 'dedicated.can_activate', false),
                'can_rotate_token' => (bool) data_get($state, 'dedicated.can_rotate_token', false),
                'ownership_verification_required' => (bool) data_get(
                    $state,
                    'dedicated.ownership_verification_required',
                    false,
                ),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $providers
     * @param  array<int, array<string, string>>  $facts
     * @return array<string, mixed>
     */
    private function step(
        string $key,
        string $label,
        string $outcome,
        string $summary,
        array $providers,
        array $facts,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'outcome' => $outcome,
            'summary' => $summary,
            'providers' => $providers,
            'facts' => $facts,
        ];
    }

    /**
     * @return array{label: string, value: string}
     */
    private function fact(string $label, string $value): array
    {
        return compact('label', 'value');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function basePayload(ScenarioRunContext $context, array $payload): array
    {
        return [
            'schema' => 'x-change.lifecycle.account-management-scenario.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'account_management',
            'operator' => [
                'id' => (string) $context->issuer->getKey(),
            ],
            'simulation' => [
                'rollback_only' => true,
                'provider_calls' => 0,
                'balance_changed' => false,
                'persisted' => false,
                'funding_instructions_issued' => false,
                'webhooks_received' => false,
            ],
            ...$payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applyScenarioConfig(): array
    {
        $original = [];

        foreach (self::SCENARIO_CONFIG as $key => $value) {
            $original[$key] = config($key);
            config()->set($key, $value);
        }

        return $original;
    }

    /**
     * @param  array<string, mixed>  $original
     */
    private function restoreConfig(array $original): void
    {
        foreach ($original as $key => $value) {
            config()->set($key, $value);
        }
    }

    private function stateDigest(Model $owner): string
    {
        $connection = $this->databases->connection();
        $ownerType = $owner::class;
        $ownerId = (string) $owner->getKey();

        $state = [
            'preferences' => $connection->table('x_change_funding_destination_preferences')
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'links' => $connection->table('xchange_provider_account_links')
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'intents' => $connection->table('x_change_funding_intents')
                ->where('created_by_type', $ownerType)
                ->where('created_by_id', $ownerId)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        ];

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
