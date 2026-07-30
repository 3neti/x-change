<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleClaimSubmitter;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
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
                $exception->getMessage(),
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

        $providerAttempts = DisbursementReconciliation::query()
            ->where('voucher_code', $context->voucher->getAttribute('code'))
            ->count();
        $executionPolicy = data_get(
            $context->voucher->metadata,
            'instructions.execution.metadata.post_redemption.mode',
        );
        $providerCallsSuppressed = $executionPolicy === OnboardingVoucherInstructionPolicy::PostRedemptionMode
            && $providerAttempts === 0;

        return new ScenarioRunResult(
            exitCode: $providerCallsSuppressed
                ? Command::SUCCESS
                : Command::FAILURE,
            payload: [
                'schema' => 'x-change.lifecycle.onboarding-voucher.v2',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => $providerCallsSuppressed,
                'message' => $providerCallsSuppressed
                    ? 'The system Account issued the onboarding Pay Code; the execution engine provisioned the recipient Account without a provider payout.'
                    : 'The onboarding account was provisioned, but the no-provider-call invariant failed.',
                'generated' => $context->generated?->toArray(),
                'issuer' => [
                    'role' => 'system',
                    'model' => $context->issuer::class,
                    'key' => (string) $context->issuer->getKey(),
                    'funding_boundary' => data_get(
                        $context->scenario,
                        'lifecycle.funding_boundary',
                    ),
                ],
                'issuance_ledger' => [
                    'currency' => $context->generated?->currency,
                    'principal_minor' => (int) round(
                        ((float) ($context->generated?->amount ?? 0)) * 100
                    ),
                    'instruction_cost_minor' => (int) round(
                        ((float) ($context->generated?->cost->total ?? 0)) * 100
                    ),
                    'account_debit_minor' => (int) round(
                        ((float) ($context->generated?->cost->account_debit ?? 0)) * 100
                    ),
                    'charges' => $context->generated?->cost->charges ?? [],
                    'wallet_before' => data_get(
                        $context->generated?->wallet,
                        'balance_before',
                    ),
                    'wallet_after' => data_get(
                        $context->generated?->wallet,
                        'balance_after',
                    ),
                ],
                'voucher' => [
                    'code' => $context->voucher->getAttribute('code'),
                    'onboarding' => data_get($context->voucher, 'instructions.onboarding') === true,
                    'execution_driver' => data_get($context->voucher, 'instructions.execution.driver'),
                    'claimed' => $result->claimed,
                    'status' => $result->status,
                    'post_redemption_mode' => $executionPolicy,
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
                    'provider_calls' => $providerAttempts > 0,
                    'provider_attempt_count' => $providerAttempts,
                    'external_payout_suppressed' => $providerCallsSuppressed,
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
        ?string $exceptionMessage = null,
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: [
                'schema' => 'x-change.lifecycle.onboarding-voucher.v2',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => false,
                'message' => $message,
                'exception' => $exception,
                'exception_message' => app()->environment(['local', 'testing'])
                    ? $exceptionMessage
                    : null,
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
