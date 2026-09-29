<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\XChange\Actions\Funding\ExpireOnDemandIssuanceFundingOrder;
use LBHurtado\XChange\Actions\Funding\FinalizeFundingSuspenseMonitoring;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final class OnDemandIssuanceFundingScenarioRunner implements ScenarioRunnerContract
{
    /** @var array<string, mixed> */
    private const ScenarioConfig = [
        'x-change.issuance_funding.on_demand.enabled' => true,
        'x-change.issuance_funding.on_demand.basis' => 'full_amount',
        'x-change.issuance_funding.on_demand.fixed_qr_ph.enabled' => true,
        'x-change.funding.requests.bank_transfer.provider' => 'qrph_simulator',
        'x-change.funding.simulator.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => true,
        'x-change.funding.simulator.signing_key' => 'on-demand-lifecycle-signing-key',
        'x-change.funding.simulator.mobile_hash_key' => 'on-demand-lifecycle-mobile-key',
        'x-change.funding.payer_identity_hash_key' => 'on-demand-lifecycle-payer-key',
    ];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly WalletAccessContract $wallets,
        private readonly PrepareOnDemandPayCodeIssuance $prepare,
        private readonly ExpireOnDemandIssuanceFundingOrder $expire,
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
            return $this->result($context, Command::FAILURE, [
                'success' => false,
                'message' => 'The rollback-only QR Ph funding simulation is disabled.',
                'steps' => [],
            ]);
        }

        $mobile = MobileNumber::normalize($context->baseClaimMobile);

        if ($mobile === null) {
            return $this->result($context, Command::FAILURE, [
                'success' => false,
                'message' => 'A valid scenario mobile is required.',
                'steps' => [],
            ]);
        }

        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($context->issuer);
        $originalConfig = $this->applyScenarioConfig();
        $exitCode = Command::SUCCESS;

        $connection->beginTransaction();

        try {
            $payload = $this->execute($context, $mobile);
        } catch (Throwable) {
            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'The on-demand fixed-amount QR Ph lifecycle could not complete safely.',
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
            $payload = [
                'success' => false,
                'message' => 'The lifecycle runner could not confirm rollback.',
                'steps' => [],
            ];
        }

        return $this->result($context, $exitCode, [
            ...$payload,
            'rollback_completed' => $rollbackCompleted,
        ]);
    }

    /** @return array<string, mixed> */
    private function execute(ScenarioRunContext $context, string $mobile): array
    {
        $owner = $context->issuer;
        $wallet = $this->wallets->resolveForUser($owner);
        $balanceBefore = (int) $this->wallets->getBalance($wallet);
        $amountMinor = (int) data_get($context->scenario, 'amount_minor', 2_500);
        $pricing = new PricingEstimateData(
            currency: 'PHP',
            pay_code_value: $amountMinor / 100,
            account_debit: $amountMinor / 100,
        );
        $instructions = [
            'cash' => ['amount' => $amountMinor / 100, 'currency' => 'PHP'],
            '_meta' => ['source' => 'on-demand-fixed-qr-lifecycle'],
        ];
        $first = $this->prepare->handle(
            $owner,
            $instructions,
            $pricing,
            $context->idempotencyKey.'-first',
        );
        $firstIntent = $first->fundingIntent;
        $qr = data_get($firstIntent?->instructions_ciphertext, 'qr_code');
        $steps = [
            $this->step('instruction_frozen', 'Pay Code instruction and price are frozen', 'protected', [
                'Required amount' => $this->money($first->required_amount_minor),
                'Funding basis' => $first->funding_basis->value,
            ]),
            $this->step('fixed_qr_issued', 'Order-specific fixed-amount QR Ph is rendered', 'ready', [
                'Embedded amount' => data_get($qr, 'embedded_amount') === true ? 'Yes' : 'No',
                'QR mode' => (string) data_get($qr, 'qr_mode', 'Unavailable'),
                'Provider calls' => 'None — rollback-only simulator',
            ]),
        ];
        $second = $this->prepare->handle(
            $owner,
            $instructions,
            $pricing,
            $context->idempotencyKey.'-second',
        );
        $steps[] = $this->step('collision_safe_lease', 'Concurrent same-value orders receive unique exact amounts', 'protected', [
            'First amount' => $this->money($first->expected_payment_minor),
            'Second amount' => $this->money($second->expected_payment_minor),
            'Collision separated' => $first->expected_payment_minor !== $second->expected_payment_minor ? 'Yes' : 'No',
        ]);

        $first->forceFill(['expires_at' => now()->subSecond()])->saveQuietly();
        $expired = $this->expire->handle($first);
        $steps[] = $this->step('order_expired', 'An unpaid order expires without issuing a Pay Code', 'expired', [
            'Order status' => $expired->status->value,
            'Pay Code issued' => $expired->voucher_id === null ? 'No' : 'Unexpectedly',
            'Lease cooling retained' => $expired->amount_lease_reusable_after?->isFuture() ? 'Yes' : 'No',
        ]);

        if (! $firstIntent instanceof FundingIntent) {
            throw new \RuntimeException('The simulated funding intent is unavailable.');
        }

        $payment = $this->simulatePayment->handle($firstIntent, $mobile);
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
        (new VerifyFundingWebhookReceiptJob($receipt->getKey()))->handle(
            $this->verifyReceipt,
            $this->settleIntent,
            $this->finalizeMonitoring,
        );
        $expired->refresh();
        $firstIntent->refresh();
        $wallet->refresh();
        $balanceAfter = (int) $this->wallets->getBalance($wallet);
        $steps[] = $this->step('late_payment_disposed', 'A verified late payment becomes Client Funds without reviving issuance', 'protected', [
            'Payment authenticated' => $authentication->authenticated ? 'Yes' : 'No',
            'Funding intent' => $firstIntent->status->value,
            'Order status' => $expired->status->value,
            'Late disposition' => (string) $expired->late_payment_disposition,
            'Pay Code issued' => $expired->voucher_id === null ? 'No' : 'Unexpectedly',
        ]);

        $success = data_get($qr, 'embedded_amount') === true
            && $first->expected_payment_minor !== $second->expected_payment_minor
            && $expired->status === PayCodeIssuanceFundingOrderStatus::Expired
            && $expired->late_payment_disposition === 'client_funds'
            && $expired->voucher_id === null
            && $firstIntent->status === FundingIntentStatus::Settled
            && $balanceAfter - $balanceBefore === $first->expected_payment_minor;

        return [
            'success' => $success,
            'message' => 'Rollback-only on-demand fixed-amount QR Ph safety lifecycle completed.',
            'steps' => $steps,
            'artifacts' => [
                'qr_present' => is_array($qr) && is_string(data_get($qr, 'base64_payload')),
                'qr_payload_sha256' => hash('sha256', (string) data_get($qr, 'base64_payload', '')),
                'first_order_reference' => $first->reference,
                'second_order_reference' => $second->reference,
            ],
        ];
    }

    /** @param array<string, string> $facts */
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

    /** @param array<string, mixed> $payload */
    private function result(ScenarioRunContext $context, int $exitCode, array $payload): ScenarioRunResult
    {
        return new ScenarioRunResult($exitCode, [
            'schema' => 'x-change.lifecycle.on-demand-fixed-qr-ph.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'on_demand_issuance_funding',
            'simulation' => [
                'rollback_only' => true,
                'provider_calls' => 0,
                'monetary_value' => false,
                'persisted' => false,
            ],
            ...$payload,
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

    private function stateDigest(Model $owner): string
    {
        $wallet = $this->wallets->resolveForUser($owner);
        $connection = $this->databases->connection();

        return hash('sha256', json_encode([
            'balance' => (int) $this->wallets->getBalance($wallet->refresh()),
            'funding_intents' => $connection->table('x_change_funding_intents')->count(),
            'funding_orders' => $connection->table('x_change_pay_code_issuance_funding_orders')->count(),
            'simulated_transactions' => $connection->table('x_change_simulated_funding_transactions')->count(),
            'webhook_receipts' => $connection->table('webhook_receipts')->count(),
            'provider_observations' => $connection->table('provider_funding_observations')->count(),
            'funding_settlements' => $connection->table('x_change_funding_settlements')->count(),
        ], JSON_THROW_ON_ERROR));
    }

    private function money(int $amountMinor): string
    {
        return '₱'.number_format($amountMinor / 100, 2);
    }
}
