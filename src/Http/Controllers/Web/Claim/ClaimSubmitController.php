<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayloadSession;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;
use LBHurtado\XChange\Support\Claim\FormFlowClaimPayloadNormalizer;

class ClaimSubmitController extends Controller
{
    public function __construct(
        protected FormFlowService $formFlowService,
        protected SubmitWebPayCodeClaim $submitAction,
        protected FormFlowClaimPayloadNormalizer $payloadNormalizer,
        protected ClaimEvidenceSynchronizer $evidenceSynchronizer,
        protected CompiledClaimResultSession $compiledClaimResultSession,
        protected ClaimApprovalResumePayloadSession $resumePayloadSession,
        protected ClaimApprovalWorkflowStoreContract $approvalWorkflows,
    ) {}

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(trim($code));
        $referenceId = $request->input('reference_id');
        $flowId = $request->input('flow_id');

        if (! $referenceId && ! $flowId) {
            return redirect()->route('x-change.claim.start', ['code' => $code])
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        $voucher = Voucher::query()->where('code', $code)->firstOrFail();

        $state = $referenceId
            ? $this->formFlowService->getFlowStateByReference($referenceId)
            : $this->formFlowService->getFlowState($flowId);

        if (! $state) {
            return redirect()->route('x-change.claim.start', ['code' => $code])
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        $payload = $this->payloadNormalizer->normalize($state['collected_data'] ?? []);

        $mobile = $payload['mobile'] ?? null;
        $country = $payload['country'] ?? 'PH';

        if (! $mobile) {
            return redirect()->route('x-change.claim.start', ['code' => $code])
                ->withErrors(['error' => 'Mobile number is required.']);
        }

        $payload['mobile'] = phone($mobile, $country)->formatE164();

        Log::info('[ClaimSubmitController] Submitting claim', [
            'voucher_code' => $code,
            'mobile' => $payload['mobile'],
            'bank_code' => $payload['bank_code'] ?? null,
            'input_keys' => array_keys($payload['inputs'] ?? []),
            'has_kyc' => isset($payload['inputs']['kyc']),
            'kyc_status' => data_get($payload, 'inputs.kyc.status'),
            'has_otp' => isset($payload['inputs']['otp']),
            'otp_verified' => data_get($payload, 'inputs.otp_verified'),
        ]);

        try {
            $this->evidenceSynchronizer->sync($payload);

            $result = $this->submitAction->handle($voucher, $payload);

            if ($result->status === 'approval_required') {
                $this->compiledClaimResultSession->put($result);
                $this->resumePayloadSession->put($voucher, $payload);
                $this->approvalWorkflows->put($voucher, [
                    'status' => 'pending',
                    'voucher_code' => (string) $voucher->code,
                    'payload' => $payload,
                    'approval' => $result->toArray(),
                    'created_at' => now()->toIso8601String(),
                ]);

                return redirect()->route('x-change.claim.approval', ['code' => $code]);
            }

            $this->formFlowService->clearFlow($state['flow_id'] ?? $flowId);

            return redirect()->route('x-change.claim.success', ['code' => $code]);
        } catch (\Throwable $e) {
            Log::error('[ClaimSubmitController] Claim failed', [
                'voucher_code' => $code,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('x-change.claim.start', [
                    'code' => $code,
                    'failed' => 1,
                ])
                ->withErrors(['code' => $e->getMessage()]);
        }
    }
}
