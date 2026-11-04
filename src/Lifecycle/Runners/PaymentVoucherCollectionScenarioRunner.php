<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\GenerateVoucherPaymentQr;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Data\Payment\RenderedVoucherPaymentQrData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\PayCodeIssuanceService;
use LBHurtado\XChange\Services\VoucherPaymentQrRendererFactory;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;
use Throwable;

final readonly class PaymentVoucherCollectionScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private PayCodeIssuanceService $issuance,
        private GenerateVoucherPaymentQr $paymentQr,
        private VoucherPaymentQrRendererFactory $qrRenderers,
        private CreatePaymentAttempt $attempts,
        private IssuePaymentInstructions $instructions,
        private VerifyPaymentAttempt $verification,
        private SimulateQrPhPayment $simulator,
        private QrPhFundingSimulatorGuard $simulatorGuard,
        private DatabaseManager $databases,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        if ((bool) data_get($context->scenario, 'payment.simulate', false)) {
            if (! $this->simulatorGuard->available() || data_get($context->scenario, 'payment.provider') !== 'qrph_simulator') {
                return new ScenarioRunResult(Command::FAILURE, ['success' => false, 'message' => 'The payable simulation is unavailable.']);
            }
            $connection = $this->databases->connection();
            $level = $connection->transactionLevel();
            $connection->beginTransaction();
            try {
                $result = $this->execute($context);
            } finally {
                while ($connection->transactionLevel() > $level) {
                    $connection->rollBack();
                }
            }

            $rollbackCompleted = $connection->transactionLevel() === $level;

            return new ScenarioRunResult(
                $rollbackCompleted ? $result->exitCode : Command::FAILURE,
                [...$result->payload, 'success' => $rollbackCompleted && ($result->payload['success'] ?? false), 'rollback_completed' => $rollbackCompleted],
            );
        }

        return $this->execute($context);
    }

    private function execute(ScenarioRunContext $context): ScenarioRunResult
    {
        try {
            $voucher = $this->issueCollectibleVoucher($context);
            $payCodeQr = $this->renderPayCodeQr($voucher);
            $attempt = $this->attempts->handle(
                voucher: $voucher,
                provider: (string) data_get($context->scenario, 'payment.provider', 'netbank'),
                browserKey: 'lifecycle-payment-browser:'.$context->idempotencyKey,
                idempotencyKey: 'lifecycle-payment-attempt:'.$context->idempotencyKey,
            );
            $attempt = $this->instructions->handle($attempt);
            if ((bool) data_get($context->scenario, 'payment.simulate', false)) {
                $waiting = $this->verification->handle($attempt, PaymentVerificationTrigger::Payer);
                if ($waiting->status !== PaymentAttemptStatus::AwaitingPayment) {
                    throw new \RuntimeException('The simulated payment did not remain safely awaiting provider evidence.');
                }
                $this->simulator->handlePaymentAttempt($attempt, $context->baseClaimMobile);
            }
            $settledAttempt = $this->maybeVerify($context, $attempt);
            if ((bool) data_get($context->scenario, 'payment.simulate', false)) {
                $replayed = $this->verification->handle($settledAttempt, PaymentVerificationTrigger::Payer);
                if ($settledAttempt->status !== PaymentAttemptStatus::Settled
                    || $settledAttempt->voucher_collection_id === null
                    || $replayed->voucher_collection_id !== $settledAttempt->voucher_collection_id
                    || VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count() !== 1) {
                    throw new \RuntimeException('Simulated payment did not settle or replay safely.');
                }
            }

            return new ScenarioRunResult(
                exitCode: Command::SUCCESS,
                payload: [
                    'success' => true,
                    'scenario' => $context->scenarioKey,
                    'label' => $context->label(),
                    'mode' => 'payment_voucher_collection',
                    'voucher' => [
                        'id' => $voucher->getKey(),
                        'code' => (string) $voucher->code,
                        'voucher_type' => (string) $voucher->voucher_type?->value,
                        'flow_type' => data_get($voucher->metadata, 'instructions.metadata.flow_type'),
                    ],
                    'artifacts' => [
                        'pay_code_qr' => [
                            'format' => $payCodeQr->format,
                            'content_type' => $payCodeQr->content_type,
                            'rendered' => $payCodeQr->rendered,
                        ],
                        'provider_payment_qr' => data_get(
                            $attempt->instructions_ciphertext,
                            'qr_code',
                        ),
                        'provider_instructions' => [
                            'provider' => data_get($attempt->instructions_ciphertext, 'provider'),
                            'amount_minor' => data_get($attempt->instructions_ciphertext, 'amount_minor'),
                            'currency' => data_get($attempt->instructions_ciphertext, 'currency'),
                            'expires_at' => data_get($attempt->instructions_ciphertext, 'expires_at'),
                        ],
                    ],
                    'payment_attempt' => [
                        'reference' => $attempt->reference,
                        'status' => $settledAttempt->status->value,
                        'expected_amount_minor' => $attempt->expected_amount_minor,
                        'provider' => $attempt->provider_code,
                        'provider_generated_qr' => data_get(
                            $attempt->instructions_ciphertext,
                            'qr_code.provider_generated',
                        ) === true,
                        'voucher_collection_id' => $settledAttempt->voucher_collection_id,
                    ],
                    'journal' => [
                        'collection_journal_expected_after_settlement' => $settledAttempt->status === PaymentAttemptStatus::Settled,
                    ],
                    'safety' => [
                        'pay_code_qr_synthesized' => false,
                        'payment_rail_qr_synthesized' => false,
                        'uses_payment_attempt_flow' => true,
                        'provider_instruction_call' => true,
                        'provider_verification_call' => (bool) data_get($context->scenario, 'payment.verify', false),
                        'recovered_after_missing_evidence' => (bool) data_get($context->scenario, 'payment.simulate', false),
                        'replay_checked' => (bool) data_get($context->scenario, 'payment.simulate', false),
                    ],
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'scenario' => $context->scenarioKey,
                    'label' => $context->label(),
                    'mode' => 'payment_voucher_collection',
                    'message' => 'The payment voucher collection lifecycle scenario could not complete safely.',
                    'error' => $exception::class,
                ],
            );
        }
    }

    private function issueCollectibleVoucher(ScenarioRunContext $context): Voucher
    {
        $walletId = data_get($context->issuer, 'wallet.id');

        if (! $walletId) {
            throw new \RuntimeException('Payment voucher scenario requires an issuer wallet.');
        }

        $issued = $this->issuance->issue($context->issuer, [
            'cash' => [
                'amount' => 0,
                'currency' => (string) data_get($context->scenario, 'currency', 'PHP'),
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
            'count' => 1,
            'prefix' => (string) data_get($context->scenario, 'prefix', 'PAY'),
            'mask' => (string) data_get($context->scenario, 'mask', '****'),
            'voucher_type' => 'payable',
            ...((array) data_get($context->scenario, 'execution', []) === [] ? [] : [
                'execution' => (array) data_get($context->scenario, 'execution'),
            ]),
            'target_amount' => (float) data_get($context->scenario, 'target_amount', 100),
            'rules' => [
                'min_payment' => (float) data_get($context->scenario, 'target_amount', 100),
                'max_payment' => (float) data_get($context->scenario, 'target_amount', 100),
                'allow_overpayment' => false,
                'auto_close_on_full_payment' => true,
            ],
            'metadata' => [
                'flow_type' => 'collectible',
                'collection_wallet_id' => $walletId,
            ],
        ]);

        return Voucher::query()->findOrFail($issued['voucher_id']);
    }

    private function renderPayCodeQr(Voucher $voucher): RenderedVoucherPaymentQrData
    {
        return $this->qrRenderers
            ->make('png_base64')
            ->render($this->paymentQr->handle($voucher));
    }

    private function maybeVerify(
        ScenarioRunContext $context,
        PaymentAttempt $attempt,
    ): PaymentAttempt {
        if (! (bool) data_get($context->scenario, 'payment.verify', false)) {
            return $attempt;
        }

        return $this->verification->handle(
            $attempt,
            PaymentVerificationTrigger::Payer,
        );
    }
}
