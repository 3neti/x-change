<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Support\Claim\ClaimApprovalOtpResultRedirector;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayload;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayloadSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

final class ClaimApprovalOtpController
{
    public function __construct(
        private readonly ClaimApprovalResumePayloadSession $resumePayloadSession,
        private readonly ClaimApprovalResumePayload $resumePayload,
        private readonly SubmitWebPayCodeClaim $submitWebPayCodeClaim,
        private readonly ClaimApprovalWorkflowStoreContract $approvalWorkflows,
    ) {}

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        $validated = $request->validate([
            'otp' => ['required', 'string'],
            'reference_id' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string', 'in:pay_codes_index'],
        ]);

        $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, $validated);
        $usedResumePayload = false;

        if (($result['status'] ?? null) === 'completed') {
            $workflow = $this->approvalWorkflows->get($voucher);
            $basePayload = $this->baseResumePayload($voucher, $workflow);

            if ($basePayload) {
                $usedResumePayload = true;
                $resumePayload = $this->resumePayload->build($voucher, array_replace_recursive(
                    $basePayload,
                    $validated,
                ));

                $result = $this->submitWebPayCodeClaim->handle($voucher, $resumePayload);

                $this->resumePayloadSession->forget($voucher);
                $this->approvalWorkflows->forget($voucher);
            }
        }

        $redirectResult = is_array($result)
            ? $result
            : $result->toArray();

        app(CompiledClaimResultSession::class)->put((object) $redirectResult);

        $redirectsToPayCodes = $this->shouldRedirectToPayCodes($request, $voucher, $validated, $redirectResult);

        Log::debug('[ClaimApprovalOtpController] Approval OTP redirect resolved', [
            'voucher_code' => (string) $voucher->code,
            'status' => data_get($redirectResult, 'status'),
            'provider' => data_get($validated, 'provider'),
            'reference_id' => data_get($validated, 'reference_id'),
            'redirect_to' => data_get($validated, 'redirect_to'),
            'referer' => $request->headers->get('referer'),
            'used_resume_payload' => $usedResumePayload,
            'redirect_target' => $redirectsToPayCodes ? 'pay_codes_index' : 'claim_result_redirector',
        ]);

        if ($redirectsToPayCodes) {
            return redirect()->route('x-change.pay-codes.index');
        }

        return app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, $redirectResult);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    private function shouldRedirectToPayCodes(Request $request, Voucher $voucher, array $payload, array $result): bool
    {
        if (! $this->completedApprovalStatus($result)) {
            return false;
        }

        if (($payload['redirect_to'] ?? null) === 'pay_codes_index') {
            return true;
        }

        return $this->isIssuerApprovalReferer($request, $voucher);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function completedApprovalStatus(array $result): bool
    {
        return in_array((string) ($result['status'] ?? ''), [
            'success',
            'completed',
            'withdrawn',
            'redeemed',
            'settled',
        ], true);
    }

    private function isIssuerApprovalReferer(Request $request, Voucher $voucher): bool
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || trim($referer) === '') {
            return false;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return $path === '/x/pay-codes/'.rawurlencode((string) $voucher->code).'/approval'
            || $path === '/x/pay-codes/'.(string) $voucher->code.'/approval';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function baseResumePayload(Voucher $voucher, ?array $workflow): ?array
    {
        $sessionPayload = $this->resumePayloadSession->get($voucher);

        if (is_array($sessionPayload)) {
            return $sessionPayload;
        }

        $workflowPayload = data_get($workflow, 'payload');

        if (is_array($workflowPayload)) {
            return $workflowPayload;
        }

        return $this->fallbackResumePayload($voucher);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallbackResumePayload(Voucher $voucher): ?array
    {
        $metadata = $voucher->fresh()?->metadata ?? $voucher->metadata;

        if (! is_array($metadata)) {
            return null;
        }

        $accountNumber = data_get($metadata, 'disbursement.recipient_identifier');
        $bankCode = data_get($metadata, 'disbursement.metadata.bank_code');

        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            return null;
        }

        if (! is_string($bankCode) || trim($bankCode) === '') {
            return null;
        }

        return [
            'amount' => data_get($metadata, 'disbursement.amount'),
            'bank_account' => [
                'bank_code' => trim($bankCode),
                'account_number' => trim($accountNumber),
                'account_name' => data_get($metadata, 'disbursement.recipient_name', 'Voucher Recipient'),
            ],
        ];
    }
}
