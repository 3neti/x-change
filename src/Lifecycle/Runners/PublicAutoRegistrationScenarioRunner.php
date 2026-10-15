<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\PublicAutoRegistrationStatus;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\PublicAutoRegistrationService;
use LBHurtado\XChange\Actions\Funding\FinalizeFundingSuspenseMonitoring;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncAdmission;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final class PublicAutoRegistrationScenarioRunner implements ScenarioRunnerContract
{
    /** @var array<string, mixed> */
    private const ScenarioConfig = [
        'x-change.public_auto_register.enabled' => true,
        'x-change.public_auto_register.provider' => 'qrph_simulator',
        'x-change.public_auto_register.amount_minor' => 10_000,
        'x-change.lifecycle.qrph_funding_simulation.enabled' => true,
        'x-change.funding.simulator.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => true,
        'x-change.funding.simulator.signing_key' => 'public-auto-register-lifecycle-signing-key',
        'x-change.funding.simulator.mobile_hash_key' => 'public-auto-register-lifecycle-mobile-key',
        'x-change.funding.payer_identity_hash_key' => 'public-auto-register-lifecycle-payer-key',
    ];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly WalletAccessContract $wallets,
        private readonly AccountBalanceReadModelContract $balances,
        private readonly PublicAutoRegistrationService $registrations,
        private readonly SimulateQrPhPayment $simulatePayment,
        private readonly QrPhSimulatorFundingProviderAdapter $adapter,
        private readonly StoreProviderWebhookReceipt $storeReceipt,
        private readonly VerifyFundingWebhookReceipt $verifyReceipt,
        private readonly FinalizeFundingSuspenseMonitoring $finalizeMonitoring,
        private readonly StandingFundingSyncAdmission $standingFundingAdmission,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! (bool) config('x-change.lifecycle.public_auto_register.enabled', false)) {
            return $this->result($context, Command::FAILURE, [
                'success' => false,
                'message' => 'The public auto-registration lifecycle simulation is disabled.',
                'steps' => [],
            ]);
        }

        $mobile = MobileNumber::normalize($context->baseClaimMobile);

        if ($mobile === null) {
            return $this->result($context, Command::FAILURE, [
                'success' => false,
                'message' => 'A valid payer mobile is required for public auto-registration.',
                'steps' => [],
            ]);
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($context->issuer);
        $originalConfig = $this->applyScenarioConfig();
        $exitCode = Command::SUCCESS;
        $result = [];

        $connection->beginTransaction();

        try {
            $result = $this->execute($context, $mobile);
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = Command::FAILURE;
            $result = [
                'success' => false,
                'message' => 'The public auto-registration lifecycle simulation failed.',
                'failure' => $exception::class.': '.$exception->getMessage(),
                'steps' => [],
            ];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }

            $this->restoreConfig($originalConfig);
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingState, $this->stateDigest($context->issuer));

        if (! $rollbackCompleted) {
            $exitCode = Command::FAILURE;
            $result = [
                'success' => false,
                'message' => 'The public auto-registration simulation could not confirm rollback.',
                'steps' => [],
            ];
        }

        return $this->result($context, $exitCode, [
            ...$result,
            'rollback_completed' => $rollbackCompleted,
        ]);
    }

    /** @return array<string, mixed> */
    private function execute(ScenarioRunContext $context, string $mobile): array
    {
        $payerName = (string) data_get($context->scenario, 'payer_name', 'Lifecycle QR Ph Payer');
        $amountMinor = (int) config('x-change.public_auto_register.amount_minor', 10_000);
        $operator = $context->issuer;
        $operatorBalanceBefore = $this->balance($operator);
        $registration = $this->registrations->start($operator, $context->idempotencyKey);
        $steps = [$this->step('registration_started', 'The real registration start action creates a QR Ph intent', 'ready', [
            'Reference' => $registration->reference,
            'Amount' => '₱'.number_format($amountMinor / 100, 2),
            'Provider' => 'qrph_simulator',
        ])];

        $payment = $this->simulatePayment->handle($registration->fundingIntent, $mobile, $payerName);
        $request = new ProviderWebhookRequestData(
            provider: 'qrph_simulator',
            rawBody: $payment->rawBody,
            contentType: 'application/json',
            headers: [],
            sourceIp: '127.0.0.1',
            receivedAt: new \DateTimeImmutable,
            signature: $payment->signature,
        );
        $authentication = $this->adapter->authenticateWebhook($request);
        $event = $this->adapter->normalizeWebhook(
            ProviderWebhookReceiptData::fromRequest($request, $authentication),
        );
        $receipt = $this->storeReceipt->handle($request, $authentication, $event);
        $steps[] = $this->step('provider_payment_mocked', 'The provider boundary returns signed settled payment evidence', 'authenticated', [
            'Payer' => $payerName,
            'Mobile' => $this->maskedMobile($mobile),
            'Signature' => $authentication->authenticated ? 'Valid' : 'Invalid',
        ]);

        (new VerifyFundingWebhookReceiptJob($receipt->getKey()))->handle(
            $this->verifyReceipt,
            app(\LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent::class),
            $this->finalizeMonitoring,
            $this->standingFundingAdmission,
        );
        $registration = $registration->fresh(['fundingIntent.settlement.providerFundingObservation']);
        $steps[] = $this->step('payment_settled', 'The real verification and settlement jobs settle the intent', $registration->fundingIntent?->status->value === 'settled' ? 'settled' : 'failed', [
            'Funding Intent' => $registration->fundingIntent?->status->value ?? 'missing',
            'Settlement' => $registration->fundingIntent?->settlement === null ? 'Missing' : 'Created',
        ]);

        $completed = $this->registrations->complete($registration, '1234');
        $userClass = (string) $completed->user_type;
        $newUser = $userClass::query()->findOrFail($completed->user_id);
        $clientBalance = $this->balance($newUser);
        $operatorBalanceAfter = $this->balance($operator);
        $steps[] = $this->step('account_created_and_funded', 'The real completion action creates the account and moves initial Client Funds', $completed->status === PublicAutoRegistrationStatus::Active && $clientBalance === $amountMinor ? 'active' : 'failed', [
            'User' => (string) $newUser->getKey(),
            'Contact' => (string) $completed->contact_id,
            'Client Funds' => '₱'.number_format($clientBalance / 100, 2),
            'Operator balance delta' => '₱'.number_format(($operatorBalanceAfter - $operatorBalanceBefore) / 100, 2),
        ]);

        $replayed = $this->registrations->complete($completed, '1234');
        $replayBalance = $this->balance($newUser->refresh());
        $steps[] = $this->step('completion_replay_noop', 'Repeating completion does not duplicate the initial credit', $replayBalance === $clientBalance ? 'protected' : 'failed', [
            'Second credit' => $replayBalance === $clientBalance ? 'No' : 'Unexpectedly yes',
            'Operation reference persisted' => filled(data_get($replayed->metadata, 'initial_client_funding.operation_reference')) ? 'Yes' : 'No',
        ]);

        return [
            'success' => $completed->status === PublicAutoRegistrationStatus::Active
                && $clientBalance === $amountMinor
                && $replayBalance === $clientBalance
                && filled(data_get($replayed->metadata, 'initial_client_funding.operation_reference')),
            'message' => 'Public auto-registration completed through mocked provider evidence and the real Treasury path.',
            'steps' => $steps,
            'balance' => [
                'operator_before_minor' => $operatorBalanceBefore,
                'operator_after_minor' => $operatorBalanceAfter,
                'recipient_minor' => $clientBalance,
                'recipient_after_replay_minor' => $replayBalance,
            ],
        ];
    }

    private function balance(Model $owner): int
    {
        return (int) ($this->balances->balanceMinor($owner, 'PHP') ?? 0);
    }

    /** @param array<string, string> $facts */
    private function step(string $key, string $label, string $outcome, array $facts): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'outcome' => $outcome,
            'facts' => collect($facts)->map(fn (string $value, string $label): array => compact('label', 'value'))->values()->all(),
        ];
    }

    /** @param array<string, mixed> $result */
    private function result(ScenarioRunContext $context, int $exitCode, array $result): ScenarioRunResult
    {
        return new ScenarioRunResult($exitCode, [
            'schema' => 'x-change.lifecycle.public-auto-register.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'public_auto_register',
            'simulation' => [
                'rollback_only' => true,
                'provider_calls' => 0,
                'signed_webhook' => true,
                'authoritative_verification' => true,
            ],
            ...$result,
        ]);
    }

    /** @return array<string, mixed> */
    private function applyScenarioConfig(): array
    {
        $original = [];

        foreach (self::ScenarioConfig as $key => $value) {
            $original[$key] = config($key);
            config()->set($key, $value);
        }

        return $original;
    }

    /** @param array<string, mixed> $original */
    private function restoreConfig(array $original): void
    {
        foreach ($original as $key => $value) {
            config()->set($key, $value);
        }
    }

    private function stateDigest(Model $issuer): string
    {
        $connection = $this->databases->connection();

        return hash('sha256', json_encode([
            'users' => $connection->table($issuer->getTable())->count(),
            'wallets' => $connection->table('wallets')->count(),
            'transactions' => $connection->table('transactions')->count(),
            'funding_intents' => $connection->table('x_change_funding_intents')->count(),
            'registrations' => $connection->table('x_change_public_auto_registrations')->count(),
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
}
