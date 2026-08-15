<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XProvisioning\Actions\AcceptProvisioningOffer;
use LBHurtado\XProvisioning\Actions\ActivateProvisioningAcceptance;
use LBHurtado\XProvisioning\Actions\ApproveProvisioningRequest;
use LBHurtado\XProvisioning\Actions\CreateProvisioningRequest;
use LBHurtado\XProvisioning\Actions\IssueProvisioningOffer;
use LBHurtado\XProvisioning\Actions\ProvisionCommissioningSeats;
use LBHurtado\XProvisioning\Actions\SubmitProvisioningRequest;
use LBHurtado\XProvisioning\Enums\ProvisioningActivationMode;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Enums\ProvisioningRequestStatus;
use Throwable;

final readonly class ProvisioningGovernanceSimulationScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private DatabaseManager $databases,
        private LifecycleScenarioBootstrapper $bootstrapper,
        private SystemUserResolverContract $systemUsers,
        private ProvisionCommissioningSeats $commissionSeats,
        private CreateProvisioningRequest $createRequest,
        private SubmitProvisioningRequest $submitRequest,
        private ApproveProvisioningRequest $approveRequest,
        private IssueProvisioningOffer $issueOffer,
        private AcceptProvisioningOffer $acceptOffer,
        private ActivateProvisioningAcceptance $activateAcceptance,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! app()->environment(['local', 'testing'])
            || ! (bool) config('x-change.lifecycle.provisioning_governance_simulation.enabled', false)) {
            return $this->failure($context, 'Provisioning governance simulation is disabled outside local and testing.');
        }

        $requiredTables = (array) config('x-change.lifecycle.provisioning_governance_simulation.required_tables', []);
        $missingTables = array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            return $this->failure($context, 'Provisioning governance simulation schema is not ready.', [
                'missing_tables' => $missingTables,
            ]);
        }

        try {
            [$maker, $checker, $candidate] = $this->actors($context);
        } catch (Throwable $exception) {
            return $this->failure($context, $exception->getMessage());
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingDigest = $this->stateDigest($requiredTables);
        $payload = [];
        $exitCode = Command::SUCCESS;
        $connection->beginTransaction();

        try {
            $payload = $this->simulate($context, $maker, $checker, $candidate);
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = Command::FAILURE;
            $payload = ['success' => false, 'message' => $exception->getMessage()];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingDigest, $this->stateDigest($requiredTables));

        if (! $rollbackCompleted) {
            return $this->failure($context, 'Provisioning governance simulation could not confirm complete rollback.');
        }

        return new ScenarioRunResult($exitCode, [
            'schema' => 'x-change.lifecycle.provisioning-governance-simulation.v1',
            'scenario' => $context->scenarioKey,
            'mode' => 'provisioning_governance_simulation',
            'safety' => [
                'provider_calls' => false,
                'real_money_movement' => false,
                'system_principal_impersonation' => false,
                'domain_authority_granted' => false,
            ],
            ...$payload,
            'persisted' => false,
            'rollback_completed' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function simulate(ScenarioRunContext $context, Model $maker, Model $checker, Model $candidate): array
    {
        $this->commissionSeats->handle(array_values((array) config('x-change.provisioning.commissioning_seats', [])));
        $profile = ProvisioningProfile::from((string) data_get($context->scenario, 'provisioning.profile', 'treasury_maker'));
        $request = $this->createRequest->handle(
            profile: $profile,
            snapshot: ['purpose' => 'Rollback-only governed provisioning simulation.'],
            maker: $maker,
            commissioning: true,
            activationMode: ProvisioningActivationMode::ReviewRequired,
        );
        $this->submitRequest->handle($request, $maker);

        $sameActorRejected = false;
        try {
            $this->approveRequest->handle($request, $maker);
        } catch (DomainException) {
            $sameActorRejected = true;
        }

        $revision = $this->approveRequest->handle($request, $checker);
        $credential = $this->issueOffer->handle($request);
        $offer = $this->acceptOffer->handle(
            claimToken: $credential->claimToken,
            candidateType: $candidate->getMorphClass(),
            candidateReference: (string) $candidate->getKey(),
            evidence: [
                'name' => (string) ($candidate->getAttribute('name') ?: 'Verified Candidate'),
                'email' => (string) ($candidate->getAttribute('email') ?: 'candidate@example.test'),
                'mobile' => (string) data_get($context->scenario, 'provisioning.mobile', '639170000001'),
                'otp' => true,
                'responsibility_attestation' => true,
            ],
        );

        $activationFailedClosed = false;
        try {
            $this->activateAcceptance->handle($offer);
        } catch (DomainException) {
            $activationFailedClosed = true;
        }

        $events = $offer->request->events()->orderBy('occurred_at')->pluck('event_type')->all();

        return [
            'success' => true,
            'actors' => [
                'maker_id' => (string) $maker->getKey(),
                'checker_id' => (string) $checker->getKey(),
                'candidate_id' => (string) $candidate->getKey(),
            ],
            'envelope' => [
                'profile' => $profile->value,
                'request_reference' => $request->reference,
                'revision' => $revision->version,
                'snapshot_hash' => $revision->snapshot_hash,
                'offer_reference' => $offer->reference,
                'status' => $offer->status->value,
                'candidate_bound' => $offer->acceptance !== null,
            ],
            'stages' => [
                ['key' => 'vacant_seat', 'result' => 'commissioned_without_identity'],
                ['key' => 'maker_request', 'result' => ProvisioningRequestStatus::AwaitingApproval->value],
                ['key' => 'independent_approval', 'result' => ProvisioningRequestStatus::Approved->value],
                ['key' => 'one_time_offer', 'result' => ProvisioningRequestStatus::Offered->value],
                ['key' => 'verified_acceptance', 'result' => ProvisioningRequestStatus::ActivationPending->value],
                ['key' => 'domain_activation', 'result' => 'fail_closed_pending_explicit_adapter'],
            ],
            'invariants' => [
                'maker_checker_separated' => ! $maker->is($checker),
                'same_actor_approval_rejected' => $sameActorRejected,
                'candidate_bound_after_evidence' => $offer->acceptance !== null,
                'activation_failed_closed_without_adapter' => $activationFailedClosed,
                'one_time_token_not_returned' => ! in_array($credential->claimToken, $events, true),
                'append_only_events_recorded' => $events === [
                    'provisioning.request.created',
                    'provisioning.request.submitted',
                    'provisioning.request.approved',
                    'provisioning.offer.issued',
                    'provisioning.offer.accepted',
                ],
            ],
            'events' => $events,
        ];
    }

    /** @return array{0: Model, 1: Model, 2: Model} */
    private function actors(ScenarioRunContext $context): array
    {
        $makerId = trim((string) data_get($context->scenario, '_runtime.maker', ''));
        $checkerId = trim((string) data_get($context->scenario, '_runtime.checker', ''));

        if ($makerId === '' || $checkerId === '') {
            throw new DomainException('Provisioning governance simulation requires --maker and --checker.');
        }

        $maker = $this->bootstrapper->resolveIssuerModel((int) $makerId);
        $checker = $this->bootstrapper->resolveIssuerModel((int) $checkerId);
        $candidate = $context->issuer;
        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model
            || $maker->is($checker)
            || $maker->is($candidate)
            || $checker->is($candidate)
            || $maker->is($system)
            || $checker->is($system)
            || $candidate->is($system)) {
            throw new DomainException('Provisioning maker, checker, candidate, and System Principal must be distinct identities.');
        }

        return [$maker, $checker, $candidate];
    }

    /** @param list<string> $tables */
    private function stateDigest(array $tables): string
    {
        $state = collect($tables)->mapWithKeys(function (string $table): array {
            if (! Schema::hasTable($table)) {
                return [$table => null];
            }

            return [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all()];
        })->all();

        return hash('sha256', serialize($state));
    }

    /** @param array<string, mixed> $details */
    private function failure(ScenarioRunContext $context, string $message, array $details = []): ScenarioRunResult
    {
        return new ScenarioRunResult(Command::FAILURE, [
            'schema' => 'x-change.lifecycle.provisioning-governance-simulation.v1',
            'scenario' => $context->scenarioKey,
            'mode' => 'provisioning_governance_simulation',
            'success' => false,
            'message' => $message,
            ...$details,
            'persisted' => false,
            'rollback_completed' => false,
        ]);
    }
}
