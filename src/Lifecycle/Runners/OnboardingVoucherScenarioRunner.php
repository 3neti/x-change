<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleClaimSubmitter;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final readonly class OnboardingVoucherScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private LifecycleClaimSubmitter $claimSubmitter,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $mobile = MobileNumber::normalize($context->baseClaimMobile);

        if ($mobile === null || ! $context->voucher instanceof Model) {
            return $this->failure(
                $context,
                'The onboarding Voucher scenario requires a valid claimant Mobile and generated Voucher.',
            );
        }

        $email = mb_strtolower(trim((string) data_get(
            $context->scenario,
            'onboarding.email',
            'onboarding-voucher-'.$mobile.'@example.test',
        )));
        $name = trim((string) data_get(
            $context->scenario,
            'onboarding.name',
            'Onboarding Voucher Recipient',
        ));

        try {
            $result = $this->claimSubmitter->submit(
                $context,
                $context->voucher,
                [
                    'mobile' => $mobile,
                    'recipient_country' => 'PH',
                    'inputs' => [
                        'full_name' => $name,
                        'name' => $name,
                        'email' => $email,
                        'mobile' => $mobile,
                        'verified_at' => now()->toIso8601String(),
                        'otp' => [
                            'verified_at' => now()->toIso8601String(),
                        ],
                        'otp_verified' => true,
                        'otp_verification' => [
                            'verified_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failure(
                $context,
                'The onboarding Voucher claim failed safely.',
                $exception::class,
            );
        }

        if (! $result instanceof SubmitPayCodeClaimResultData || ! $result->claimed) {
            return $this->failure(
                $context,
                'The onboarding Voucher did not reach a completed claim.',
            );
        }

        $account = $this->accountFor($context, $mobile);

        if (! $account instanceof Model) {
            return $this->failure(
                $context,
                'The onboarding Voucher was claimed without a resolvable recipient Account.',
            );
        }

        return new ScenarioRunResult(
            exitCode: Command::SUCCESS,
            payload: [
                'schema' => 'x-change.lifecycle.onboarding-voucher.v1',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => true,
                'message' => 'The onboarding Voucher provisioned or reused the recipient Account and completed through the execution engine.',
                'generated' => $context->generated?->toArray(),
                'voucher' => [
                    'code' => $context->voucher->getAttribute('code'),
                    'onboarding' => data_get($context->voucher, 'instructions.onboarding') === true,
                    'execution_driver' => data_get($context->voucher, 'instructions.execution.driver'),
                    'claimed' => $result->claimed,
                    'status' => $result->status,
                ],
                'recipient_account' => [
                    'model' => $account::class,
                    'key' => (string) $account->getKey(),
                    'mobile' => $this->maskedMobile($mobile),
                    'mobile_verified' => $account->getAttribute('mobile_verified_at') !== null,
                    'platform_account_ready' => method_exists($account, 'wallet')
                        && $account->wallet()->where('slug', 'platform')->exists(),
                ],
                'controls' => [
                    'provider_calls' => false,
                    'raw_otp_persisted' => $this->rawOtpPersisted($context->voucher),
                    'canonical_claim_link' => route(
                        'x-change.claim.show',
                        ['code' => $context->voucher->getAttribute('code')],
                        false,
                    ),
                ],
            ],
        );
    }

    private function accountFor(ScenarioRunContext $context, string $mobile): ?Model
    {
        $modelClass = $context->issuer::class;

        return $modelClass::query()
            ->whereIn('mobile', MobileNumber::candidates($mobile))
            ->first();
    }

    private function failure(
        ScenarioRunContext $context,
        string $message,
        ?string $exception = null,
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: [
                'schema' => 'x-change.lifecycle.onboarding-voucher.v1',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => false,
                'message' => $message,
                'exception' => $exception,
                'provider_calls' => false,
            ],
        );
    }

    private function maskedMobile(string $mobile): string
    {
        return str_repeat('*', max(0, mb_strlen($mobile) - 4)).mb_substr($mobile, -4);
    }

    private function rawOtpPersisted(Model $voucher): bool
    {
        $metadata = $voucher->redeemers()->latest('id')->first()?->metadata;
        $inputs = (array) data_get(
            $metadata,
            'redemption.inputs',
            data_get($metadata, 'inputs', []),
        );
        $otp = data_get($inputs, 'otp');
        $otpValue = data_get($inputs, 'otp.value');

        return filled(data_get($inputs, 'otp_code'))
            || filled(data_get($inputs, 'otp.otp_code'))
            || (is_string($otp) && $otp !== 'verified' && trim($otp) !== '')
            || (is_string($otpValue) && $otpValue !== 'verified' && trim($otpValue) !== '');
    }
}
