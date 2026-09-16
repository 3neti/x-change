<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\PayCodeIssuanceService;
use Throwable;

final readonly class AuiInsuranceAcquisitionScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private PayCodeIssuanceService $issuance,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        try {
            $voucher = $this->issueApplicationVoucher($context);
            $claimUrl = $this->absoluteUrl($this->claimPath((string) $voucher->code));
            $payUrl = $this->absoluteUrl($this->payPath((string) $voucher->code));

            return new ScenarioRunResult(
                exitCode: Command::SUCCESS,
                payload: [
                    'success' => true,
                    'scenario' => $context->scenarioKey,
                    'label' => $context->label(),
                    'mode' => 'aui_insurance_acquisition',
                    'campaign' => [
                        'usage' => 'acquisition',
                        'entry_point' => 'scenario_runner',
                        'simulates_public_endpoint' => true,
                        'public_endpoint_implemented' => false,
                    ],
                    'voucher' => [
                        'id' => $voucher->getKey(),
                        'code' => (string) $voucher->code,
                        'voucher_type' => (string) $voucher->voucher_type?->value,
                        'flow_type' => data_get($voucher->metadata, 'instructions.metadata.flow_type'),
                        'amount' => data_get($voucher->instructions?->toArray(), 'cash.amount'),
                        'target_amount' => data_get($voucher->instructions?->toArray(), 'target_amount'),
                        'currency' => data_get($voucher->instructions?->toArray(), 'cash.currency'),
                    ],
                    'urls' => [
                        'claim' => $claimUrl,
                        'pay' => $payUrl,
                    ],
                    'applicant' => $this->applicant($context),
                    'requirements' => [
                        'form_flow_fields' => (array) data_get($voucher->instructions?->toArray(), 'inputs.fields', []),
                        'aui_domain_fields' => (array) data_get($voucher->metadata, 'instructions.metadata.custom.aui.application_fields', []),
                    ],
                    'manual_browser_checkpoints' => [
                        'Open the claim URL and verify the applicant intake requirements.',
                        'Complete the claim or document the first missing product gap.',
                        'Open the pay URL and verify the payment page / QR Ph surface.',
                    ],
                    'safety' => [
                        'provider_payment_attempt_created' => false,
                        'provider_payment_qr_requested' => false,
                        'claim_completed_by_runner' => false,
                        'payment_completed_by_runner' => false,
                        'uses_existing_claim_route' => true,
                        'uses_existing_pay_route' => true,
                        'settlement_first' => true,
                    ],
                    'product_gaps_deferred' => [
                        'public merchant campaign endpoint',
                        'post-claim Continue to payment CTA',
                        'X-Ray next-action projection',
                        'Campaign monitor status display',
                        'settlement-first AUI premium allocation model',
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
                    'mode' => 'aui_insurance_acquisition',
                    'message' => 'The AUI insurance acquisition scenario could not issue a safe application Pay Code.',
                    'error' => $exception::class,
                ],
            );
        }
    }

    private function issueApplicationVoucher(ScenarioRunContext $context): Voucher
    {
        $issued = $this->issuance->issue($context->issuer, [
            'cash' => [
                'amount' => (float) data_get($context->scenario, 'amount', 0),
                'currency' => (string) data_get($context->scenario, 'currency', 'PHP'),
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => [
                'fields' => (array) data_get($context->scenario, 'application.form_flow_fields', [
                    'name',
                    'mobile',
                    'email',
                    'address',
                    'birth_date',
                    'otp',
                ]),
            ],
            'feedback' => [],
            'rider' => [
                'message' => (string) data_get(
                    $context->scenario,
                    'rider.message',
                    'Complete your AUI insurance application, then continue to payment.',
                ),
            ],
            'count' => 1,
            'prefix' => (string) data_get($context->scenario, 'prefix', 'AUI'),
            'mask' => (string) data_get($context->scenario, 'mask', '****'),
            'voucher_type' => (string) data_get($context->scenario, 'voucher_type', 'settlement'),
            'target_amount' => (float) data_get($context->scenario, 'target_amount', 100),
            'rules' => [
                'min_payment' => (float) data_get($context->scenario, 'target_amount', 100),
                'max_payment' => (float) data_get($context->scenario, 'target_amount', 100),
                'allow_overpayment' => false,
                'auto_close_on_full_payment' => true,
            ],
            'metadata' => [
                'flow_type' => (string) data_get($context->scenario, 'metadata.flow_type', 'settlement'),
                'custom' => [
                    'scenario' => $context->scenarioKey,
                    'campaign_usage' => 'acquisition',
                    'settlement_preferred' => (bool) data_get($context->scenario, 'settlement_preferred', true),
                    'aui' => [
                        'schema' => 'x-change.lifecycle.aui-insurance-acquisition.v1',
                        'applicant' => $this->applicant($context),
                        'application_fields' => (array) data_get($context->scenario, 'application.domain_fields', [
                            'vehicle_registration',
                            'plate_number',
                            'license_number',
                        ]),
                    ],
                ],
            ],
        ]);

        return Voucher::query()->findOrFail($issued['voucher_id']);
    }

    /** @return array<string, mixed> */
    private function applicant(ScenarioRunContext $context): array
    {
        return [
            'name' => (string) data_get($context->scenario, 'applicant.name', 'Apple Hurtado'),
            'mobile' => (string) data_get($context->scenario, 'applicant.mobile', '09175180722'),
            'email' => (string) data_get($context->scenario, 'applicant.email', 'apple.hurtado@example.test'),
            'address' => (string) data_get($context->scenario, 'applicant.address', 'AUI scenario test address'),
            'birth_date' => (string) data_get($context->scenario, 'applicant.birth_date', '1990-01-01'),
            'vehicle_registration' => (string) data_get($context->scenario, 'applicant.vehicle_registration', 'AUI-TEST-REG-001'),
            'plate_number' => (string) data_get($context->scenario, 'applicant.plate_number', 'AUI1234'),
            'license_number' => (string) data_get($context->scenario, 'applicant.license_number', 'N01-00-000000'),
        ];
    }

    private function claimPath(string $code): string
    {
        if (Route::has('x-change.claim.show')) {
            return route('x-change.claim.show', ['code' => $code], false);
        }

        return '/x/claim/'.urlencode($code);
    }

    private function payPath(string $code): string
    {
        if (Route::has('x-change.pay.show')) {
            return route('x-change.pay.show', ['code' => $code], false);
        }

        return '/x/pay/'.urlencode($code);
    }

    private function absoluteUrl(string $path): string
    {
        $base = rtrim((string) config('app.url', ''), '/');

        return $base === '' ? $path : $base.$path;
    }
}
