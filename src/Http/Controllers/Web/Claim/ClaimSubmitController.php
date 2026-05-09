<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use Propaganistas\LaravelPhone\PhoneNumber;

class ClaimSubmitController extends Controller
{
    public function __construct(
        protected FormFlowService $formFlowService,
        protected SubmitPayCodeClaim $submitAction,
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

        // Retrieve collected data from form-flow session
        $state = $referenceId
            ? $this->formFlowService->getFlowStateByReference($referenceId)
            : $this->formFlowService->getFlowState($flowId);

        if (! $state) {
            return redirect()->route('x-change.claim.start', ['code' => $code])
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }

        $collectedData = $state['collected_data'] ?? [];

        // Flatten collected data from form-flow steps
        $flatData = $this->flattenCollectedData($collectedData);

        $mobile = $flatData['mobile'] ?? null;
        $country = $flatData['recipient_country'] ?? 'PH';

        if (! $mobile) {
            return redirect()->route('x-change.claim.start', ['code' => $code])
                ->withErrors(['error' => 'Mobile number is required.']);
        }

        // Build claim payload matching SubmitPayCodeClaim expectations
        $payload = [
            'mobile' => (string) (new PhoneNumber($mobile, $country)),
            'country' => $country,
            'bank_code' => $flatData['bank_code'] ?? null,
            'account_number' => $flatData['account_number'] ?? null,
            'inputs' => collect($flatData)
                ->except(['mobile', 'recipient_country', 'bank_code', 'account_number', 'amount', 'settlement_rail'])
                ->toArray(),
        ];

        Log::info('[ClaimSubmitController] Submitting claim', [
            'voucher_code' => $code,
            'mobile' => $mobile,
            'bank_code' => $payload['bank_code'],
        ]);

        try {
            $result = $this->submitAction->handle($voucher, $payload);

            // Clear form-flow session
            $this->formFlowService->clearFlow($state['flow_id']);

            return redirect()->route('x-change.claim.success', ['code' => $code])
                ->with('success', 'Pay Code claimed successfully!');
        } catch (\Throwable $e) {
            Log::error('[ClaimSubmitController] Claim failed', [
                'voucher_code' => $code,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('x-change.claim.start', ['code' => $code])
                ->withErrors(['error' => 'Failed to process claim: '.$e->getMessage()]);
        }
    }

    protected function flattenCollectedData(array $collectedData): array
    {
        $mapped = [];

        foreach ($collectedData as $stepData) {
            if (is_array($stepData)) {
                $mapped = array_merge($mapped, $stepData);
            }
        }

        // Apply field name mappings from config
        $fieldMappings = (array) config('x-change.redemption.field_mappings', []);

        foreach ($fieldMappings as $from => $to) {
            if (isset($mapped[$from]) && ! isset($mapped[$to])) {
                $mapped[$to] = $mapped[$from];
            }
        }

        return $mapped;
    }
}
