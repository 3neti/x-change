<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XAffiliation\Models\AffiliationMembership;
use LBHurtado\XAffiliation\Models\AffiliationPath;
use LBHurtado\XAffiliation\Models\AffiliationSponsorship;
use LBHurtado\XChange\Actions\Affiliation\ActivateVoucherSponsorship;
use LBHurtado\XChange\Actions\Affiliation\CreateSponsorshipInvitationAuthority;
use LBHurtado\XChange\Actions\Affiliation\EnrollAffiliationRootAccount;
use LBHurtado\XChange\Actions\Affiliation\LinkSponsorshipAuthorityToVoucher;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use Throwable;

final readonly class AffiliationNetworkingSimulationScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private DatabaseManager $databases,
        private LifecycleScenarioBootstrapper $bootstrapper,
        private SystemUserResolverContract $systemUsers,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private EnrollAffiliationRootAccount $enrollRoot,
        private CreateSponsorshipInvitationAuthority $createAuthority,
        private LinkSponsorshipAuthorityToVoucher $linkAuthority,
        private ActivateVoucherSponsorship $activateSponsorship,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! app()->environment(['local', 'testing'])
            || ! (bool) config('x-change.lifecycle.affiliation_networking_simulation.enabled', false)) {
            return $this->failure($context, 'Affiliation networking simulation is disabled outside local and testing.');
        }

        $requiredTables = (array) config('x-change.lifecycle.affiliation_networking_simulation.required_tables', []);
        $missingTables = array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            return $this->failure($context, 'Affiliation networking simulation schema is not ready.', [
                'missing_tables' => $missingTables,
            ]);
        }

        try {
            [$sponsor, $maker, $checker] = $this->actors($context);
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
            $payload = $this->simulate($context, $sponsor, $maker, $checker);
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
            return $this->failure($context, 'Affiliation networking simulation could not confirm complete rollback.');
        }

        return new ScenarioRunResult($exitCode, [
            'schema' => 'x-change.lifecycle.affiliation-networking-simulation.v1',
            'scenario' => $context->scenarioKey,
            'mode' => 'affiliation_networking_simulation',
            'safety' => [
                'provider_calls' => false,
                'real_money_movement' => false,
                'raw_mobile_output' => false,
                'relationships_persisted' => false,
            ],
            ...$payload,
            'persisted' => false,
            'rollback_completed' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function simulate(
        ScenarioRunContext $context,
        Model $sponsor,
        Model $maker,
        Model $checker,
    ): array {
        $scope = substr(hash('sha256', $context->idempotencyKey), 0, 12);
        $bob = $this->candidate('Bob Member', "bob-{$scope}@example.test", '09175180722');
        $carol = $this->candidate('Carol Member', "carol-{$scope}@example.test", '09171234567');

        $root = $this->enrollRoot->handle(
            $sponsor,
            "lifecycle:affiliation-networking:{$scope}:root",
        );

        $bobResult = $this->sponsor(
            sponsor: $sponsor,
            recipient: $bob,
            recipientMobile: '09175180722',
            maker: $maker,
            checker: $checker,
            voucherCode: 'AFB'.strtoupper(substr($scope, 0, 5)),
        );
        $bobReplay = $this->activateSponsorship->handle(
            $bobResult['voucher'],
            $bob,
            $this->claimEvidence($bob),
        );
        $carolResult = $this->sponsor(
            sponsor: $bob,
            recipient: $carol,
            recipientMobile: '09171234567',
            maker: $maker,
            checker: $checker,
            voucherCode: 'AFC'.strtoupper(substr($scope, 0, 5)),
        );

        $bobMembership = $this->membership($bob);
        $carolMembership = $this->membership($carol);
        $relationships = AffiliationPath::query()
            ->with(['ancestor', 'descendant'])
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->map(fn (AffiliationPath $path): array => [
                'from' => $this->labelForMembership($path->ancestor, $root, $bobMembership, $carolMembership),
                'to' => $this->labelForMembership($path->descendant, $root, $bobMembership, $carolMembership),
                'depth' => $path->depth,
            ])
            ->values()
            ->all();

        $cycleRejected = false;

        try {
            $this->createAuthority->handle($carol, $this->mobile($sponsor), $maker, $checker);
        } catch (DomainException) {
            $cycleRejected = true;
        }

        return [
            'success' => true,
            'message' => 'Affiliation sponsorship authority produced Alice to Bob to Carol lineage and rejected a cycle.',
            'actors' => [
                'sponsor' => 'Alice',
                'maker_id' => (string) $maker->getKey(),
                'checker_id' => (string) $checker->getKey(),
                'maker_checker_separated' => ! $maker->is($checker),
            ],
            'stages' => [
                ['key' => 'root_adoption', 'result' => 'Alice enrolled'],
                ['key' => 'maker_request', 'result' => 'Bob invitation created'],
                ['key' => 'checker_approval', 'result' => 'Bob invitation approved independently'],
                ['key' => 'verified_activation', 'result' => 'Alice sponsored Bob'],
                ['key' => 'second_generation', 'result' => 'Bob sponsored Carol'],
                ['key' => 'cycle_guard', 'result' => 'Carol could not sponsor Alice'],
            ],
            'artifacts' => [
                'bob_authority_reference' => $bobResult['offer']->activation_reference,
                'carol_authority_reference' => $carolResult['offer']->activation_reference,
                'bob_snapshot_hash' => $bobResult['authority']->snapshot_hash,
                'carol_snapshot_hash' => $carolResult['authority']->snapshot_hash,
            ],
            'graph' => [
                'membership_count' => AffiliationMembership::query()->count(),
                'direct_sponsorship_count' => AffiliationSponsorship::query()->count(),
                'path_count' => AffiliationPath::query()->count(),
                'relationships' => $relationships,
            ],
            'invariants' => [
                'maker_checker_separated' => ! $maker->is($checker),
                'root_enrolled' => $root->exists,
                'activation_replay_idempotent' => $bobReplay?->is($bobResult['offer']) === true,
                'alice_to_bob_direct' => $this->pathExists($root, $bobMembership, 1),
                'bob_to_carol_direct' => $this->pathExists($bobMembership, $carolMembership, 1),
                'alice_to_carol_depth_two' => $this->pathExists($root, $carolMembership, 2),
                'cycle_rejected' => $cycleRejected,
                'raw_mobile_absent_from_authority_snapshots' => ! str_contains(
                    json_encode([
                        $bobResult['offer']->revision->snapshot,
                        $carolResult['offer']->revision->snapshot,
                    ], JSON_THROW_ON_ERROR),
                    '0917',
                ),
            ],
        ];
    }

    /**
     * @return array{voucher: Voucher, authority: mixed, offer: mixed}
     */
    private function sponsor(
        Model $sponsor,
        Model $recipient,
        string $recipientMobile,
        Model $maker,
        Model $checker,
        string $voucherCode,
    ): array {
        $credential = $this->createAuthority->handle($sponsor, $recipientMobile, $maker, $checker);
        $voucher = Voucher::query()->create([
            'code' => $voucherCode,
            'state' => 'active',
            'metadata' => ['instructions' => ['onboarding' => true]],
        ]);
        $authority = $this->linkAuthority->handle($voucher, $credential);
        $offer = $this->activateSponsorship->handle($voucher, $recipient, $this->claimEvidence($recipient));

        if ($offer === null) {
            throw new DomainException('Affiliation invitation did not activate.');
        }

        return compact('voucher', 'authority', 'offer');
    }

    /** @return array{name: string, email: string, mobile: string, otp: bool} */
    private function claimEvidence(Model $candidate): array
    {
        return [
            'name' => (string) $candidate->getAttribute('name'),
            'email' => (string) $candidate->getAttribute('email'),
            'mobile' => $this->mobile($candidate),
            'otp' => true,
        ];
    }

    private function candidate(string $name, string $email, string $mobile): Model
    {
        $class = $this->bootstrapper->userModelClass();
        $candidate = new $class;
        $candidate->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => str()->random(64),
        ])->save();

        if ($candidate instanceof HasMobileChannel) {
            $candidate->setMobileChannel($mobile);
        } else {
            $candidate->setAttribute('mobile', $mobile);
        }

        $candidate->forceFill(['mobile_verified_at' => now()])->save();

        return $candidate;
    }

    /** @return array{0: Model, 1: Model, 2: Model} */
    private function actors(ScenarioRunContext $context): array
    {
        $makerId = trim((string) data_get($context->scenario, '_runtime.maker', ''));
        $checkerId = trim((string) data_get($context->scenario, '_runtime.checker', ''));

        if ($makerId === '' || $checkerId === '') {
            throw new DomainException('Affiliation networking simulation requires --maker and --checker.');
        }

        $sponsor = $context->issuer;
        $maker = $this->bootstrapper->resolveIssuerModel((int) $makerId);
        $checker = $this->bootstrapper->resolveIssuerModel((int) $checkerId);
        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model
            || $maker->is($checker)
            || $maker->is($system)
            || $checker->is($system)
            || $sponsor->is($system)) {
            throw new DomainException('Sponsor, Maker, and Checker must be eligible human Accounts with independent Maker and Checker identities.');
        }

        if ($this->mobile($sponsor) === '') {
            throw new DomainException('The affiliation sponsor requires a verified mobile identity.');
        }

        return [$sponsor, $maker, $checker];
    }

    private function mobile(Model $account): string
    {
        $mobile = $account instanceof HasMobileChannel
            ? $account->getMobileChannel()
            : $account->getAttribute('mobile');

        return is_string($mobile) ? trim($mobile) : '';
    }

    private function membership(Model $account): AffiliationMembership
    {
        return AffiliationMembership::query()
            ->where('subject_reference', $this->principalReferences->resolve($account))
            ->firstOrFail();
    }

    private function pathExists(
        AffiliationMembership $ancestor,
        AffiliationMembership $descendant,
        int $depth,
    ): bool {
        return AffiliationPath::query()
            ->where('ancestor_membership_id', $ancestor->getKey())
            ->where('descendant_membership_id', $descendant->getKey())
            ->where('depth', $depth)
            ->exists();
    }

    private function labelForMembership(
        AffiliationMembership $membership,
        AffiliationMembership $alice,
        AffiliationMembership $bob,
        AffiliationMembership $carol,
    ): string {
        return match ((string) $membership->getKey()) {
            (string) $alice->getKey() => 'Alice',
            (string) $bob->getKey() => 'Bob',
            (string) $carol->getKey() => 'Carol',
            default => 'Unknown',
        };
    }

    /** @param list<string> $tables */
    private function stateDigest(array $tables): string
    {
        $state = collect($tables)->mapWithKeys(function (string $table): array {
            if (! Schema::hasTable($table)) {
                return [$table => null];
            }

            return [$table => DB::table($table)->orderBy('id')->get()->map(
                static fn (object $row): array => (array) $row,
            )->all()];
        })->all();

        return hash('sha256', serialize($state));
    }

    /** @param array<string, mixed> $details */
    private function failure(ScenarioRunContext $context, string $message, array $details = []): ScenarioRunResult
    {
        return new ScenarioRunResult(Command::FAILURE, [
            'schema' => 'x-change.lifecycle.affiliation-networking-simulation.v1',
            'scenario' => $context->scenarioKey,
            'mode' => 'affiliation_networking_simulation',
            'success' => false,
            'message' => $message,
            ...$details,
            'persisted' => false,
            'rollback_completed' => false,
        ]);
    }
}
