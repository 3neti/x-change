<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Output\ConsoleLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayload;

final class LifecycleApprovalOtpCompleter
{
    public function __construct(
        private readonly SubmitClaimApprovalOtp $submitApprovalOtp,
        private readonly ClaimApprovalResumePayload $resumePayload,
        private readonly SubmitWebPayCodeClaim $submitWebPayCodeClaim,
    ) {}

    /**
     * @param  array<string, mixed>  $baseClaimPayload
     */
    public function complete(
        ScenarioRunContext $context,
        Voucher $voucher,
        ClaimApprovalInitiationResultData $approval,
        array $baseClaimPayload,
    ): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData {
        if ($context->wantsJson()) {
            return $approval;
        }

        $command = $this->command($context);

        if (! $command) {
            return $approval;
        }

        $meta = $approval->meta ?? [];

        $referenceId = (string) data_get($meta, 'reference_id', '');
        $provider = (string) data_get($meta, 'provider', 'paynamics');

        $context->output->warn('Approval required.');
        $context->output->line('Provider: '.$provider);

        if ($referenceId !== '') {
            $context->output->line('Reference: '.$referenceId);
        }

        $otp = trim((string) $command->ask('Enter approval OTP'));

        if ($otp === '') {
            return $approval;
        }

        $otpResult = $this->submitApprovalOtp->handle($voucher, [
            ...$baseClaimPayload,
            'otp' => $otp,
            'reference_id' => $referenceId,
            'provider' => $provider,
        ]);

        if (($otpResult['status'] ?? null) !== 'completed') {
            return ClaimApprovalInitiationResultData::from([
                'voucher_code' => (string) $voucher->code,
                'status' => (string) ($otpResult['status'] ?? 'pending_approval'),
                'requirements' => ['otp'],
                'actions' => ['otp'],
                'meta' => data_get($otpResult, 'approval_metadata', $meta),
                'messages' => data_get($otpResult, 'messages', ['Approval OTP not completed.']),
            ]);
        }

        $replayPayload = $this->resumePayload->build($voucher, array_replace_recursive(
            $baseClaimPayload,
            [
                'otp' => $otp,
                'reference_id' => $referenceId,
                'provider' => $provider,
            ],
        ));

        return $this->submitWebPayCodeClaim->handle($voucher, $replayPayload);
    }

    private function command(ScenarioRunContext $context): ?Command
    {
        if (! $context->output instanceof ConsoleLifecycleOutput) {
            return null;
        }

        return $context->output->command();
    }
}
