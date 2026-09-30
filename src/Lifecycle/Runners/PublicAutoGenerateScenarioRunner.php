<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\FinalizeFundingSuspenseMonitoring;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\CommercialPrincipalResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Cockpit\CompileCockpitQuickGenerateClaimPolicy;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class PublicAutoGenerateScenarioRunner implements ScenarioRunnerContract
{
    /** @var array<string, mixed> */
    private const ScenarioConfig = [
        'x-change.public_auto_generate.enabled' => true,
        'x-change.issuance_funding.on_demand.enabled' => true,
        'x-change.issuance_funding.on_demand.basis' => 'full_amount',
        'x-change.issuance_funding.on_demand.fixed_qr_ph.enabled' => true,
        'x-change.funding.requests.bank_transfer.provider' => 'qrph_simulator',
        'x-change.funding.simulator.enabled' => true,
        'x-change.funding.providers.qrph_simulator.enabled' => true,
        'x-change.funding.simulator.signing_key' => 'public-auto-generate-lifecycle-signing-key',
        'x-change.funding.simulator.mobile_hash_key' => 'public-auto-generate-lifecycle-mobile-key',
        'x-change.funding.payer_identity_hash_key' => 'public-auto-generate-lifecycle-payer-key',
        'x-change.provider_runtime.providers.netbank.source_account_readiness.enabled' => false,
        'x-change.commercial.enabled' => true,
        'x-change.commercial.billing.mode' => 'billable',
        'x-change.deployment.runtime_tier' => 'testing',
    ];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly CommercialPrincipalResolverContract $principals,
        private readonly WalletAccessContract $wallets,
        private readonly CompileCockpitQuickGenerateClaimPolicy $claimPolicy,
        private readonly EstimatePayCodeCost $estimate,
        private readonly PrepareOnDemandPayCodeIssuance $prepare,
        private readonly SimulateQrPhPayment $simulatePayment,
        private readonly QrPhSimulatorFundingProviderAdapter $adapter,
        private readonly StoreProviderWebhookReceipt $storeReceipt,
        private readonly VerifyFundingWebhookReceipt $verifyReceipt,
        private readonly SettleVerifiedFundingIntent $settleIntent,
        private readonly FinalizeFundingSuspenseMonitoring $finalizeMonitoring,
        private readonly QrPhFundingSimulatorGuard $simulatorGuard,
        private readonly PublicIssuanceOrderAccess $orderAccess,
        private readonly OnDemandIssuanceFundingOrderPresenter $presenter,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if (! (bool) config('x-change.lifecycle.qrph_funding_simulation.enabled', false)) {
            return $this->result($context, Command::FAILURE, [
                'success' => false,
                'message' => 'The rollback-only public issuance simulation is disabled.',
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

        $principal = $this->principals->resolve();
        $connection = $this->databases->connection();
        $startingLevel = $connection->transactionLevel();
        $startingState = $this->stateDigest($principal);
        $originalConfig = $this->applyScenarioConfig();
        $exitCode = Command::SUCCESS;

        $connection->beginTransaction();

        try {
            $payload = $this->simulatorGuard->withinRollbackLifecycle(
                fn (): array => $this->execute($context, $principal, $mobile),
            );
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'The public Auto-Generate lifecycle could not complete safely.',
                'failure' => $exception::class,
                'failure_message' => $exception->getMessage(),
                'validation_errors' => $exception instanceof ValidationException
                    ? $exception->errors()
                    : [],
                'steps' => [],
            ];
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }

            $this->restoreConfig($originalConfig);
        }

        $rollbackCompleted = $connection->transactionLevel() === $startingLevel
            && hash_equals($startingState, $this->stateDigest($principal));

        if (! $rollbackCompleted) {
            $exitCode = Command::FAILURE;
            $payload = [
                'success' => false,
                'message' => 'The public lifecycle runner could not confirm rollback.',
                'steps' => [],
            ];
        }

        return $this->result($context, $exitCode, [
            ...$payload,
            'rollback_completed' => $rollbackCompleted,
        ]);
    }

    /** @return array<string, mixed> */
    private function execute(
        ScenarioRunContext $context,
        CommercialPrincipal $principal,
        string $mobile,
    ): array {
        $amountMinor = (int) data_get($context->scenario, 'amount_minor', 2_500);
        $instructions = $this->claimPolicy->handle($this->instructions($amountMinor));
        $pricing = $this->estimate->handle($instructions);
        $requiredAmountMinor = (int) round((float) $pricing->account_debit * 100);
        $voucherCountBefore = Voucher::query()->count();
        $order = $this->prepare->handle(
            issuer: $principal,
            instructions: $instructions,
            pricing: $pricing,
            idempotencyKey: $context->idempotencyKey,
            fundingBasis: OnDemandIssuanceFundingBasis::FullAmount,
            requirePayerIdentityMatch: false,
        );
        $replayed = $this->prepare->handle(
            issuer: $principal,
            instructions: $instructions,
            pricing: $pricing,
            idempotencyKey: $context->idempotencyKey,
            fundingBasis: OnDemandIssuanceFundingBasis::FullAmount,
            requirePayerIdentityMatch: false,
        );
        $browser = $this->browserRequest('public-auto-generate-primary');
        $token = $this->orderAccess->bind($order, $browser);
        $browser->headers->set(PublicIssuanceOrderAccess::TokenHeader, $token);
        $otherBrowser = $this->browserRequest('public-auto-generate-other');
        $otherBrowser->headers->set(PublicIssuanceOrderAccess::TokenHeader, $token);
        $isolated = false;

        try {
            $this->orderAccess->authorize($order->refresh(), $otherBrowser);
        } catch (NotFoundHttpException) {
            $isolated = true;
        }

        $intent = $order->fundingIntent;

        if ($intent === null) {
            throw new \RuntimeException('The public funding intent is unavailable.');
        }

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
        (new VerifyFundingWebhookReceiptJob($receipt->getKey()))->handle(
            $this->verifyReceipt,
            $this->settleIntent,
            $this->finalizeMonitoring,
        );

        $order->refresh();

        if ($order->status !== PayCodeIssuanceFundingOrderStatus::Funded) {
            throw new \RuntimeException(sprintf(
                'The public funding order did not reach the funded state [%s; intent=%s].',
                $order->status->value,
                (string) $intent->refresh()->status->value,
            ));
        }

        app()->forgetScopedInstances();
        Auth::forgetGuards();
        $issuance = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
        app()->call([$issuance, 'handle']);
        app()->call([$issuance, 'handle']);
        $order->refresh();
        $projection = $this->presenter->present($order, $token, true);
        $voucherCountAfter = Voucher::query()->count();
        $qr = data_get($intent->refresh()->instructions_ciphertext, 'qr_code');
        $steps = [
            $this->step('principal_resolved', 'Commercial Principal resolved by the server', 'protected', [
                'Principal' => $principal->reference,
                'Browser selectable' => 'No',
            ]),
            $this->step('authoritative_price', 'Principal and instruction fees form one complete total', 'ready', [
                'Pay Code principal' => $this->money($amountMinor),
                'Service and instruction fees' => $this->money($requiredAmountMinor - $amountMinor),
                'Total required now' => $this->money($requiredAmountMinor),
            ]),
            $this->step('guest_order_bound', 'Funding order is bound to one browser and possession token', 'protected', [
                'Other browser rejected' => $isolated ? 'Yes' : 'No',
                'Raw token persisted' => data_get($order->metadata, 'public_auto_generate.token_hash') === $token ? 'Unexpectedly' : 'No',
            ]),
            $this->step('idempotent_resume', 'Repeating preparation resumes the same frozen order', 'protected', [
                'Same order' => $replayed->is($order) ? 'Yes' : 'No',
            ]),
            $this->step('exact_payment', 'Exact simulated QR Ph payment is verified and held', 'verified', [
                'Payment authenticated' => $authentication->authenticated ? 'Yes' : 'No',
                'Embedded amount' => data_get($qr, 'embedded_amount') === true ? 'Yes' : 'No',
                'Provider calls' => 'None — rollback-only simulator',
            ]),
            $this->step('pay_code_ready', 'Exactly one Pay Code and normal stamp/share result are produced', 'ready', [
                'Order status' => $order->status->value,
                'Issued once' => $voucherCountAfter - $voucherCountBefore === 1 ? 'Yes' : 'No',
                'Stamp available' => is_string(data_get($projection, 'order.voucher.claim_qr')) ? 'Yes' : 'No',
            ]),
        ];
        $success = $isolated
            && $replayed->is($order)
            && $order->issuer_type === CommercialPrincipal::class
            && $order->issuer_id === (string) $principal->getKey()
            && $order->funding_basis === OnDemandIssuanceFundingBasis::FullAmount
            && $order->required_amount_minor === $requiredAmountMinor
            && $order->status === PayCodeIssuanceFundingOrderStatus::Issued
            && $voucherCountAfter - $voucherCountBefore === 1
            && data_get($projection, 'lifecycle.current') === 'pay_code_ready'
            && is_string(data_get($projection, 'order.voucher.claim_qr'))
            && is_string(data_get($projection, 'order.voucher.share_card_url'));

        return [
            'success' => $success,
            'message' => 'Rollback-only public Auto-Generate lifecycle completed.',
            'steps' => $steps,
            'artifacts' => [
                'commercial_principal_reference' => $principal->reference,
                'order_reference' => $order->reference,
                'pay_code' => data_get($projection, 'order.voucher.code'),
                'claim_qr_present' => is_string(data_get($projection, 'order.voucher.claim_qr')),
                'share_result_present' => is_string(data_get($projection, 'order.voucher.share_card_url')),
                'expected_payment_minor' => $order->expected_payment_minor,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function instructions(int $amountMinor): array
    {
        return [
            'cash' => [
                'amount' => $amountMinor / 100,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => ['fields' => ['selfie']],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'count' => 1,
            'provider' => 'netbank',
            'prefix' => 'PUBLIC',
            'mask' => '****',
            'voucher_type' => 'redeemable',
            'metadata' => ['authorization' => 'public_auto_generate'],
            '_meta' => ['source' => 'public.auto-generate'],
        ];
    }

    private function browserRequest(string $sessionName): Request
    {
        $request = Request::create('/x/auto-generate', 'GET');
        $request->setLaravelSession(new Store(
            $sessionName,
            new ArraySessionHandler(120),
        ));
        $request->session()->start();

        return $request;
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
            'schema' => 'x-change.lifecycle.public-auto-generate.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => 'public_auto_generate',
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

    private function stateDigest(CommercialPrincipal $principal): string
    {
        $wallet = $this->wallets->resolveForUser($principal);
        $connection = $this->databases->connection();

        return hash('sha256', json_encode([
            'balance' => (int) $this->wallets->getBalance($wallet->refresh()),
            'vouchers' => Voucher::query()->count(),
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
