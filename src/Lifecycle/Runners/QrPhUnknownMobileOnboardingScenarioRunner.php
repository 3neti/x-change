<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use LBHurtado\XChange\Actions\Auth\StartMobileVerification;
use LBHurtado\XChange\Actions\Auth\VerifyMobileVerification;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\NullWithdrawalOtpApprovalService;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final class QrPhUnknownMobileOnboardingScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly WalletAccessContract $wallets,
        private readonly NullWithdrawalOtpApprovalService $simulatedOtp,
        private readonly QrPhFundingSimulationScenarioRunner $funding,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! (bool) config('x-change.lifecycle.qrph_funding_simulation.enabled', false)) {
            return $this->failure(
                $context,
                'The QR Ph funding lifecycle simulation is disabled.',
                true,
            );
        }

        $mobile = MobileNumber::normalize($context->baseClaimMobile);

        if ($mobile === null) {
            return $this->failure(
                $context,
                'The unknown-mobile onboarding scenario requires a valid mobile.',
                true,
            );
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($context->issuer);
        $originalOtpDriver = config('x-change.withdrawal.otp.driver');
        $result = [];
        $exitCode = Command::SUCCESS;

        config()->set('x-change.withdrawal.otp.driver', 'null');
        $connection->beginTransaction();

        try {
            $result = $this->execute($context, $mobile);
        } catch (Throwable) {
            $exitCode = Command::FAILURE;
            $result = [
                'success' => false,
                'message' => 'The unknown-mobile onboarding simulation could not complete safely.',
                'steps' => [],
            ];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }

            config()->set('x-change.withdrawal.otp.driver', $originalOtpDriver);
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingState, $this->stateDigest($context->issuer));

        if (! $rollbackCompleted) {
            return $this->failure(
                $context,
                'The unknown-mobile onboarding simulation could not confirm rollback.',
                false,
            );
        }

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: $this->payload($context, [
                ...$result,
                'rollback_completed' => true,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(ScenarioRunContext $context, string $mobile): array
    {
        $userModel = $context->issuer::class;

        if ($userModel::query()->where('mobile', $mobile)->exists()) {
            throw new \LogicException('The scenario mobile must be unknown.');
        }

        $intentCountBefore = FundingIntent::query()->count();
        $steps = [
            $this->step(
                'payer_mobile_submitted',
                'Payer mobile is submitted before provider payment',
                'received',
                [
                    'Mobile' => $this->maskedMobile($mobile),
                    'Provider payment' => 'Not started',
                ],
            ),
            $this->step(
                'unknown_mobile_blocked',
                'Unknown mobile stops before Funding Intent creation',
                'protected',
                [
                    'Account resolved' => 'No',
                    'Funding Intent created' => 'No',
                    'Balance changed' => 'No',
                ],
            ),
        ];

        /** @var Model $newUser */
        $newUser = new $userModel;
        $newUser->forceFill([
            'name' => 'QR Ph Onboarding Simulation',
            'email' => 'qrph-onboarding-'.str()->lower((string) str()->ulid()).'@example.test',
            'password' => Hash::make((string) str()->random(40)),
            'mobile' => $mobile,
            'mobile_verified_at' => null,
        ])->save();
        $steps[] = $this->step(
            'mobile_user_onboarded',
            'A pending mobile user is onboarded explicitly',
            'pending_verification',
            [
                'Webhook created user' => 'No',
                'Mobile verified' => 'No',
                'Funding Intent created' => 'No',
            ],
        );
        $challenge = (new StartMobileVerification($this->simulatedOtp))->handle($newUser);
        (new VerifyMobileVerification($this->simulatedOtp))->handle($newUser, '000000');
        $challenge->refresh();
        $newUser->refresh();

        if (! method_exists($newUser, 'wallet')) {
            throw new \LogicException('The onboarded user cannot open an Account wallet.');
        }

        $newUser->wallet()->firstOrCreate([
            'slug' => 'platform',
        ], [
            'name' => 'Platform Wallet',
        ]);
        $newUser->unsetRelation('wallet');
        $this->wallets->resolveForUser($newUser);
        $steps[] = $this->step(
            'mobile_verified_and_account_opened',
            'OTP verification opens the Account funding path',
            'verified',
            [
                'Challenge' => $challenge->status === 'verified' ? 'Verified' : 'Completed',
                'Mobile verified' => $newUser->getAttribute('mobile_verified_at') !== null ? 'Yes' : 'No',
                'Account' => 'Ready',
            ],
        );

        if (FundingIntent::query()->count() !== $intentCountBefore) {
            throw new \LogicException('Onboarding must not create a Funding Intent.');
        }

        $fundingResult = $this->funding->run(new ScenarioRunContext(
            output: $context->output,
            scenarioKey: $context->scenarioKey,
            scenario: [
                ...$context->scenario,
                '_runtime' => [
                    ...(array) data_get($context->scenario, '_runtime', []),
                    'rollback_managed_by_parent' => true,
                ],
            ],
            issuer: $newUser,
            generated: null,
            voucher: null,
            attempts: [],
            baseClaimMobile: $mobile,
            estimate: [],
            idempotencyKey: $context->idempotencyKey.'-funding',
            readiness: $context->readiness,
        ));
        $fundingSteps = (array) data_get($fundingResult->payload, 'steps', []);
        $fundingSucceeded = $fundingResult->exitCode === Command::SUCCESS
            && data_get($fundingResult->payload, 'success') === true
            && data_get($fundingResult->payload, 'rollback_deferred_to_parent') === true;

        return [
            'success' => $fundingSucceeded,
            'message' => 'Unknown mobile stopped, completed explicit onboarding, and resumed through verified QR Ph funding.',
            'steps' => [...$steps, ...$fundingSteps],
            'funding' => [
                'credited_minor' => data_get($fundingResult->payload, 'balance.credited_minor'),
                'replay_noop' => data_get($fundingResult->payload, 'balance.after_minor')
                    === data_get($fundingResult->payload, 'balance.after_replay_minor'),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $facts
     * @return array<string, mixed>
     */
    private function step(string $key, string $label, string $outcome, array $facts): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'outcome' => $outcome,
            'facts' => collect($facts)
                ->map(fn (string $value, string $label): array => compact('label', 'value'))
                ->values()
                ->all(),
        ];
    }

    private function stateDigest(Model $issuer): string
    {
        $connection = $this->databases->connection();
        $wallet = $this->wallets->resolveForUser($issuer);

        return hash('sha256', json_encode([
            'users' => $connection->table($issuer->getTable())->count(),
            'wallets' => $connection->table('wallets')->count(),
            'transactions' => $connection->table('transactions')->count(),
            'issuer_balance' => (int) $this->wallets->getBalance($wallet->refresh()),
            'mobile_challenges' => $connection->table('x_change_mobile_verification_challenges')->count(),
            'funding_intents' => $connection->table('x_change_funding_intents')->count(),
            'simulated_transactions' => $connection->table('x_change_simulated_funding_transactions')->count(),
            'webhook_receipts' => $connection->table('webhook_receipts')->count(),
            'provider_observations' => $connection->table('provider_funding_observations')->count(),
            'funding_settlements' => $connection->table('x_change_funding_settlements')->count(),
        ], JSON_THROW_ON_ERROR));
    }

    private function maskedMobile(string $mobile): string
    {
        return substr($mobile, 0, 2).'••••••'.substr($mobile, -4);
    }

    private function failure(
        ScenarioRunContext $context,
        string $message,
        bool $rollbackCompleted,
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: $this->payload($context, [
                'success' => false,
                'message' => $message,
                'steps' => [],
                'rollback_completed' => $rollbackCompleted,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function payload(ScenarioRunContext $context, array $result): array
    {
        return [
            'schema' => 'x-change.lifecycle.qrph-unknown-mobile-onboarding.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'qrph_unknown_mobile_onboarding',
            'simulation' => [
                'rollback_only' => true,
                'provider_calls' => 0,
                'webhook_user_creation' => false,
                'payment_before_identity' => false,
                'persisted' => false,
            ],
            ...$result,
        ];
    }
}
