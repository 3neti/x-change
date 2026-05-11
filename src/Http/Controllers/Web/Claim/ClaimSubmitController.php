<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
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

        $phoneNumber = (string) (new PhoneNumber($mobile, $country));

        // Build claim payload matching SubmitPayCodeClaim expectations.
        // The 'inputs' array must include ALL collected fields (including mobile)
        // because InputsSpecification checks voucher.instructions.inputs.fields
        // against context.inputs.
        $inputs = $this->buildInputs($flatData, $collectedData);

        $payload = [
            'mobile' => $phoneNumber,
            'country' => $country,
            'bank_code' => $flatData['bank_code'] ?? null,
            'account_number' => $flatData['account_number'] ?? null,
            'inputs' => $inputs,
        ];

        Log::info('[ClaimSubmitController] Submitting claim', [
            'voucher_code' => $code,
            'mobile' => $mobile,
            'bank_code' => $payload['bank_code'],
            'input_keys' => array_keys($inputs),
            'has_kyc' => isset($inputs['kyc']),
            'kyc_status' => data_get($inputs, 'kyc.status'),
        ]);

        $this->syncApprovedKycToContact(
            phoneNumber: $phoneNumber,
            country: $country,
            kycData: $inputs['kyc'] ?? [],
        );

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

    protected function buildInputs(array $flatData, array $collectedData): array
    {
        $inputs = collect($flatData)
            ->except(['recipient_country', 'amount', 'settlement_rail'])
            ->toArray();

        $kycData = $this->extractKycData($flatData, $collectedData);

        if ($kycData !== []) {
            $inputs['kyc'] = $kycData;

            foreach ([
                'transaction_id',
                'status',
                'completed_at',
                'rejection_reasons',
                'name',
                'email',
                'date_of_birth',
                'birth_date',
                'address',
                'id_type',
                'id_number',
                'nationality',
                'id_card_full',
                'id_card_cropped',
                'selfie',
            ] as $key) {
                if (array_key_exists($key, $kycData) && ! array_key_exists($key, $inputs)) {
                    $inputs[$key] = $kycData[$key];
                }
            }
        }

        return $inputs;
    }

    protected function extractKycData(array $flatData, array $collectedData): array
    {
        $candidates = [];

        if (isset($collectedData['kyc_verification']) && is_array($collectedData['kyc_verification'])) {
            $candidates[] = $collectedData['kyc_verification'];
        }

        if (isset($flatData['kyc']) && is_array($flatData['kyc'])) {
            $candidates[] = $flatData['kyc'];
        }

        $flatKycKeys = [
            'transaction_id',
            'status',
            'completed_at',
            'rejection_reasons',
            'name',
            'email',
            'date_of_birth',
            'birth_date',
            'address',
            'id_type',
            'id_number',
            'nationality',
            'id_card_full',
            'id_card_cropped',
            'selfie',
        ];

        $flatCandidate = [];

        foreach ($flatKycKeys as $key) {
            if (array_key_exists($key, $flatData)) {
                $flatCandidate[$key] = $flatData[$key];
            }
        }

        if ($flatCandidate !== []) {
            $candidates[] = $flatCandidate;
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeKycData($candidate);

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    protected function normalizeKycData(array $kycData): array
    {
        if (isset($kycData['kyc']) && is_array($kycData['kyc'])) {
            $kycData = array_merge($kycData['kyc'], $kycData);
            unset($kycData['kyc']);
        }

        if (isset($kycData['status']) && is_string($kycData['status'])) {
            $kycData['status'] = strtolower($kycData['status']);
        }

        if (($kycData['status'] ?? null) === 'auto_approved') {
            $kycData['status'] = 'approved';
        }

        if (($kycData['status'] ?? null) === 'success') {
            $kycData['status'] = 'approved';
        }

        if (! isset($kycData['transaction_id']) && isset($kycData['transactionId'])) {
            $kycData['transaction_id'] = $kycData['transactionId'];
        }

        if (! isset($kycData['completed_at'])) {
            $kycData['completed_at'] = now()->toIso8601String();
        }

        return array_filter(
            $kycData,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    protected function syncApprovedKycToContact(string $phoneNumber, string $country, array $kycData): void
    {
        $status = strtolower((string) ($kycData['status'] ?? ''));

        if ($status !== 'approved') {
            return;
        }

        try {
            $contact = Contact::fromPhoneNumber(phone($phoneNumber, $country));

            $contact->forceFill([
                'kyc_status' => 'approved',
                'kyc_transaction_id' => $kycData['transaction_id'] ?? $contact->kyc_transaction_id,
                'kyc_submitted_at' => $contact->kyc_submitted_at ?? now(),
                'kyc_completed_at' => $kycData['completed_at'] ?? now(),
                'kyc_rejection_reasons' => null,
            ])->save();

            Log::info('[ClaimSubmitController] Synced approved KYC to contact', [
                'contact_id' => $contact->id,
                'mobile' => $phoneNumber,
                'country' => $country,
                'transaction_id' => $kycData['transaction_id'] ?? null,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ClaimSubmitController] Failed to sync KYC to contact', [
                'mobile' => $phoneNumber,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
