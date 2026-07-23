<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\FinalizeFundingSuspenseMonitoring;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final class QrPhFundingSimulationScenarioRunner implements ScenarioRunnerContract
{
    /**
     * @var array<string, mixed>
     */
    private const ScenarioConfig = [
        'x-change.funding.simulator.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => true,
        'x-change.funding.simulator.signing_key' => 'qrph-lifecycle-simulator-signing-key',
        'x-change.funding.simulator.mobile_hash_key' => 'qrph-lifecycle-simulator-mobile-key',
        'x-change.funding.payer_identity_hash_key' => 'qrph-lifecycle-payer-identity-key',
    ];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly WalletAccessContract $wallets,
        private readonly FundingDestinationResolverContract $destinations,
        private readonly CreateFundingIntent $createFundingIntent,
        private readonly IssueFundingInstructions $issueFundingInstructions,
        private readonly SimulateQrPhPayment $simulatePayment,
        private readonly QrPhSimulatorFundingProviderAdapter $adapter,
        private readonly StoreProviderWebhookReceipt $storeReceipt,
        private readonly VerifyFundingWebhookReceipt $verifyReceipt,
        private readonly SettleVerifiedFundingIntent $settleIntent,
        private readonly FinalizeFundingSuspenseMonitoring $finalizeMonitoring,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! (bool) config('x-change.lifecycle.qrph_funding_simulation.enabled', false)) {
            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: $this->payload($context, [
                    'success' => false,
                    'message' => 'The QR Ph funding lifecycle simulation is disabled.',
                    'steps' => [],
                ]),
            );
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($context->issuer);
        $originalConfig = $this->applyScenarioConfig();
        $result = [];
        $exitCode = Command::SUCCESS;

        $connection->beginTransaction();

        try {
            $result = $this->execute($context);
        } catch (Throwable) {
            $exitCode = Command::FAILURE;
            $result = [
                'success' => false,
                'message' => 'The QR Ph funding lifecycle simulation could not complete safely.',
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
                'message' => 'The QR Ph funding lifecycle simulation could not confirm rollback.',
                'steps' => [],
            ];
        }

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: $this->payload($context, [
                ...$result,
                'rollback_completed' => $rollbackCompleted,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(ScenarioRunContext $context): array
    {
        $owner = $context->issuer;
        $mobile = MobileNumber::normalize($context->baseClaimMobile);

        if ($mobile === null) {
            throw new \InvalidArgumentException('The scenario requires a valid mobile number.');
        }

        $owner->forceFill([
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
        ])->save();
        $wallet = $this->wallets->resolveForUser($owner);
        $accountReference = 'wallet:'.$wallet->uuid;
        $balanceBefore = (int) $this->wallets->getBalance($wallet);
        $amountMinor = (int) data_get($context->scenario, 'amount_minor', 2_500);
        $steps = [
            $this->step(
                'verified_mobile_resolved',
                'Verified mobile resolves the intended Account',
                'ready',
                [
                    'Payer mobile' => $this->maskedMobile($mobile),
                    'Account' => 'Resolved',
                    'Settlement authority' => 'Provider evidence only',
                ],
            ),
        ];
        $destination = $this->destinations->shared('qrph_simulator', $accountReference);
        $intent = $this->createFundingIntent->handle(new CreateFundingIntentData(
            accountReference: $accountReference,
            provider: 'qrph_simulator',
            expectedAmountMinor: $amountMinor,
            currency: 'PHP',
            idempotencyKey: $context->idempotencyKey,
            actorType: $owner::class,
            actorId: (string) $owner->getKey(),
            metadata: [
                'source' => 'qrph-funding-lifecycle-simulation',
                'rollback_only' => true,
            ],
            destination: $destination,
        ));
        $steps[] = $this->step(
            'funding_intent_created',
            'Funding Intent is created before simulated settlement',
            'protected',
            [
                'Amount' => '₱'.number_format($amountMinor / 100, 2),
                'Currency' => 'PHP',
                'Balance changed' => 'No',
            ],
        );
        $intent = $this->issueFundingInstructions->handle(
            $intent,
            $owner::class,
            (string) $owner->getKey(),
        );
        $steps[] = $this->step(
            'qr_instructions_issued',
            'One-time QR Ph simulation instructions are issued',
            'ready',
            [
                'Provider' => 'QR Ph Simulator',
                'Reference' => (string) $intent->provider_request_id,
                'Balance changed' => 'No',
            ],
        );
        $payment = $this->simulatePayment->handle($intent, $mobile);
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
        $steps[] = $this->step(
            'signed_webhook_received',
            'Signed provider evidence is preserved before credit',
            'authenticated',
            [
                'Authentication' => $authentication->authenticated ? 'Passed' : 'Failed',
                'Receipt' => 'Stored',
                'Balance changed' => 'No',
            ],
        );
        $this->runVerificationJob($receipt->getKey());
        $intent = FundingIntent::query()->findOrFail($intent->getKey());
        $wallet->refresh();
        $balanceAfter = (int) $this->wallets->getBalance($wallet);
        $steps[] = $this->step(
            'provider_evidence_verified',
            'Independent simulated-provider ledger verification completes',
            $intent->status === FundingIntentStatus::Settled ? 'verified' : 'failed',
            [
                'Payer identity' => 'Full verified mobile matched',
                'Amount and currency' => 'Matched',
                'Destination' => 'Matched',
            ],
        );
        $steps[] = $this->step(
            'treasury_and_account_credited',
            'Treasury Inventory recognition and Account credit are atomic',
            $intent->status === FundingIntentStatus::Settled ? 'settled' : 'failed',
            [
                'Balance before' => '₱'.number_format($balanceBefore / 100, 2),
                'Balance after' => '₱'.number_format($balanceAfter / 100, 2),
                'Credited once' => $balanceAfter - $balanceBefore === $amountMinor ? 'Yes' : 'No',
            ],
        );
        $this->runVerificationJob($receipt->getKey());
        $wallet->refresh();
        $balanceAfterReplay = (int) $this->wallets->getBalance($wallet);
        $steps[] = $this->step(
            'identical_replay_noop',
            'Identical callback replay is a no-op',
            $balanceAfterReplay === $balanceAfter ? 'protected' : 'failed',
            [
                'Second credit' => $balanceAfterReplay === $balanceAfter ? 'No' : 'Unexpectedly',
                'Final balance' => '₱'.number_format($balanceAfterReplay / 100, 2),
            ],
        );

        return [
            'success' => $intent->status === FundingIntentStatus::Settled
                && $balanceAfter - $balanceBefore === $amountMinor
                && $balanceAfterReplay === $balanceAfter,
            'message' => 'Rollback-only QR Ph funding lifecycle completed.',
            'steps' => $steps,
            'balance' => [
                'before_minor' => $balanceBefore,
                'after_minor' => $balanceAfter,
                'credited_minor' => $balanceAfter - $balanceBefore,
                'after_replay_minor' => $balanceAfterReplay,
            ],
        ];
    }

    private function runVerificationJob(int $receiptId): void
    {
        (new VerifyFundingWebhookReceiptJob($receiptId))->handle(
            $this->verifyReceipt,
            $this->settleIntent,
            $this->finalizeMonitoring,
        );
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

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function payload(ScenarioRunContext $context, array $result): array
    {
        return [
            'schema' => 'x-change.lifecycle.qrph-funding-simulation.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'qrph_funding_simulation',
            'simulation' => [
                'rollback_only' => true,
                'provider_calls' => 0,
                'simulated_provider_ledger' => true,
                'signed_webhook' => true,
                'authoritative_verification' => true,
                'persisted' => false,
            ],
            ...$result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applyScenarioConfig(): array
    {
        $original = [];

        foreach (self::ScenarioConfig as $key => $value) {
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
        $wallet = $this->wallets->resolveForUser($owner);
        $connection = $this->databases->connection();

        return hash('sha256', json_encode([
            'owner' => (array) $connection->table($owner->getTable())->find($owner->getKey()),
            'balance' => (int) $this->wallets->getBalance($wallet->refresh()),
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
}
