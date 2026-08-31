<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetImportRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetIntakeRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetImportData;
use LBHurtado\XCampaign\Data\CampaignWorksheetIntakeData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XCampaign\Data\CampaignWorksheetSummaryData;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCampaignWorksheetRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCampaignWorksheetRowRequest;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityRequirementResolver;

class CockpitCampaignWorksheetController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly CampaignWorksheetImportRepository $imports,
        private readonly CampaignWorksheetIntakeRepository $intakes,
        private readonly InstructionCapabilityReadinessRegistry $instructionCapabilities,
        private readonly InstructionCapabilityRequirementResolver $instructionCapabilityRequirements,
    ) {}

    public function index(Request $request): Response
    {
        $owner = $request->user();

        return Inertia::render('x-change/cockpit/Campaigns', [
            'worksheets' => $this->summariesFor($owner),
            'active_intake' => $this->activeIntakeFor($owner),
        ]);
    }

    public function store(CreateCampaignWorksheetRequest $request): RedirectResponse
    {
        $owner = $request->user();
        $validated = $request->validated();
        $worksheet = $this->worksheets->put(new CampaignWorksheetData(
            reference: null,
            ownerType: $this->ownerType($owner),
            ownerId: (string) $owner->getAuthIdentifier(),
            profile: $validated['profile'],
            name: $validated['name'],
            fulfillmentMode: $validated['fulfillment_mode'],
            deliveryPlan: $validated['delivery_plan'],
        ));

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', sprintf('%s is ready for beneficiary entries.', $worksheet->name));
    }

    public function show(Request $request, string $worksheet): Response
    {
        $worksheetReadModel = $this->worksheetFor($worksheet, $request->user());

        return Inertia::render('x-change/cockpit/CampaignWorksheet', [
            'worksheet' => $worksheetReadModel,
            'imports' => $this->importsFor($worksheet, $request->user()),
            'fulfillment_summary' => $this->fulfillmentSummaryFor($worksheet, $request->user()),
            'authorization' => $this->authorizationFor($worksheet, $request->user()),
            'fulfillments' => $this->fulfillmentsFor($worksheet, $request->user()),
            'direct_bank_transfer_enabled' => (bool) config('x-change.campaigns.netbank_dispatch.enabled', false),
            'onboarding_otp_required' => (bool) config('x-change.onboarding.voucher.require_otp', false),
            'delivery' => $this->deliveryFor($worksheet, $request->user()),
            'instruction_capabilities' => $this->instructionCapabilities->sanitized(),
            'instruction_capability_blockers' => $this->instructionCapabilityBlockers(
                (array) data_get($worksheetReadModel, 'instruction_blueprint', []),
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return list<string>
     */
    private function instructionCapabilityBlockers(array $instructions): array
    {
        return collect($this->instructionCapabilityRequirements->forInstructions($instructions))
            ->map(fn (string $key) => $this->instructionCapabilities->find($key))
            ->filter(fn ($capability): bool => $capability !== null && ! $capability->issuanceAllowed)
            ->map(fn ($capability): string => $capability->reason ?? sprintf('%s is unavailable.', $capability->label))
            ->values()
            ->all();
    }

    public function destroy(Request $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $campaign = $this->worksheets->findForOwner(
            $worksheet,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        );
        abort_unless($campaign instanceof CampaignWorksheetData, 404);
        if ($campaign->status !== 'draft') {
            return to_route('x-change.cockpit.campaigns.index')
                ->withErrors(['campaign' => 'Only a draft Campaign may be deleted.']);
        }

        try {
            $this->worksheets->deleteDraft(
                $worksheet,
                $this->ownerType($owner),
                (string) $owner->getAuthIdentifier(),
            );
        } catch (InvalidArgumentException $exception) {
            return to_route('x-change.cockpit.campaigns.index')
                ->withErrors(['campaign' => $exception->getMessage()]);
        }

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', sprintf('%s was deleted.', $campaign->name));
    }

    public function addRow(
        CreateCampaignWorksheetRowRequest $request,
        string $worksheet,
    ): RedirectResponse {
        $owner = $request->user();
        $validated = $request->validated();
        $campaign = $this->worksheets->appendRow(
            $worksheet,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
            new CampaignWorksheetRowData(
                reference: null,
                ordinal: 0,
                beneficiary: array_filter([
                    'name' => $validated['name'] ?: null,
                    'mobile' => $validated['mobile'] ?: null,
                    'bank_account' => $validated['bank_account'] ?: null,
                    'bank_code' => $validated['bank_code'] ?: null,
                    'email' => $validated['email'] ?: null,
                    'remarks' => $validated['remarks'] ?: null,
                    'external_reference' => $validated['external_reference'] ?: null,
                ]),
                amountMinor: $validated['amount_minor'],
                deliveryPreference: $validated['delivery_preference'],
            ),
        );

        return to_route('x-change.cockpit.campaigns.show', $campaign->reference)
            ->with('campaign_notice', 'Beneficiary added to the draft worksheet.');
    }

    /**
     * @return array<int, array<string, int|string|null|array<int, string>>>
     */
    private function summariesFor(mixed $owner): array
    {
        return array_map(
            fn (CampaignWorksheetSummaryData $worksheet): array => [
                'reference' => $worksheet->reference,
                'profile' => $worksheet->profile,
                'name' => $worksheet->name,
                'currency' => $worksheet->currency,
                'status' => $worksheet->status,
                'fulfillment_mode' => $worksheet->fulfillmentMode,
                'delivery_plan' => $worksheet->deliveryPlan,
                'beneficiary_count' => $worksheet->beneficiaryCount,
                'principal_minor' => $worksheet->principalMinor,
                'updated_at' => $worksheet->updatedAt,
            ],
            $this->worksheets->summariesForOwner(
                $this->ownerType($owner),
                (string) $owner->getAuthIdentifier(),
            ),
        );
    }

    private function ownerType(mixed $owner): string
    {
        return $owner instanceof Model ? $owner->getMorphClass() : $owner::class;
    }

    /** @return array<string, mixed> */
    private function activeIntakeFor(mixed $owner): array
    {
        $intake = $this->intakes->activeForOwner(
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        );
        if (! $intake instanceof CampaignWorksheetIntakeData) {
            return [];
        }

        $validRows = collect($intake->rows)->where('status', 'valid');
        $invalidRows = collect($intake->rows)->where('status', 'invalid');

        return [
            'reference' => $intake->reference,
            'source_name' => $intake->sourceName,
            'source_format' => $intake->sourceFormat,
            'source_headers' => $intake->sourceHeaders,
            'source_sheet' => $intake->sourceSheet,
            'row_count' => $intake->rowCount,
            'mapping' => $intake->mapping,
            'suggestion' => $intake->suggestion,
            'valid_count' => $validRows->count(),
            'invalid_count' => $invalidRows->count(),
            'valid_principal_minor' => $validRows->sum(
                fn (array $row): int => (int) data_get($row, 'normalized.amount_minor', 0),
            ),
            'valid_source_rows' => $validRows->pluck('source_row')->map(fn (mixed $row): int => (int) $row)->values()->all(),
            'rows' => collect($intake->rows)->take(100)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function worksheetFor(string $reference, mixed $owner): array
    {
        $worksheet = $this->worksheets->findForOwner(
            $reference,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        );

        abort_unless($worksheet instanceof CampaignWorksheetData, 404);

        return [
            'reference' => $worksheet->reference,
            'profile' => $worksheet->profile,
            'name' => $worksheet->name,
            'currency' => $worksheet->currency,
            'status' => $worksheet->status,
            'fulfillment_mode' => $worksheet->fulfillmentMode,
            'delivery_plan' => $worksheet->deliveryPlan,
            'pay_code_template_reference' => $worksheet->payCodeTemplateReference,
            'instruction_blueprint' => $worksheet->instructionBlueprint,
            'instruction_blueprint_hash' => $worksheet->instructionBlueprintHash,
            'instruction_blueprint_schema' => $worksheet->instructionBlueprintSchema,
            'instruction_blueprint_revision' => $worksheet->instructionBlueprintRevision,
            'manifest_hash' => $worksheet->manifestHash,
            'rows' => array_map(fn (CampaignWorksheetRowData $row): array => [
                'reference' => $row->reference,
                'ordinal' => $row->ordinal,
                'beneficiary' => $row->beneficiary,
                'amount_minor' => $row->amountMinor,
                'delivery_preference' => $row->deliveryPreference,
                'status' => $row->status,
            ], $worksheet->rows),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function importsFor(string $reference, mixed $owner): array
    {
        return array_values(array_filter(array_map(function (CampaignWorksheetImportData $import): ?array {
            if ($import->status === 'discarded') {
                return null;
            }

            $unapplied = collect($import->stagedRows)->whereNull('applied_at');
            $valid = $unapplied->where('status', 'valid');
            $mapping = array_filter(
                $import->mapping,
                fn (string $key): bool => ! str_starts_with($key, '__'),
                ARRAY_FILTER_USE_KEY,
            );

            return [
                'reference' => $import->reference,
                'status' => $import->status,
                'source_format' => $import->sourceFormat,
                'source_sheet' => $import->sourceSheet,
                'source_headers' => $import->sourceHeaders,
                'row_count' => $import->rowCount,
                'valid_count' => count($import->validRows),
                'unapplied_valid_count' => $valid->count(),
                'invalid_count' => $unapplied->where('status', 'invalid')->count(),
                'valid_principal_minor' => $valid->sum(
                    fn (array $row): int => (int) data_get($row, 'normalized.amount_minor', 0),
                ),
                'validation_errors' => $import->validationErrors,
                'mapping' => $mapping,
                'default_wallet' => $import->mapping['__default_wallet'] ?? 'GCash',
                'default_delivery_preference' => $import->mapping['__default_delivery_preference'] ?? 'manual',
                'preview' => $unapplied->take(50)->values()->all(),
            ];
        }, $this->imports->forOwner(
            $reference,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        ))));
    }

    /** @return array<string, int> */
    private function fulfillmentSummaryFor(string $reference, mixed $owner): array
    {
        $worksheet = CampaignWorksheet::query()
            ->where('reference', $reference)
            ->where('owner_type', $this->ownerType($owner))
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->first();
        if (! $worksheet instanceof CampaignWorksheet) {
            return [];
        }

        return $worksheet->authorizations()
            ->withCount([
                'fulfillments as planned_count' => fn ($query) => $query->where('status', 'planned'),
                'fulfillments as issued_count' => fn ($query) => $query->where('status', 'issued'),
                'fulfillments as completed_count' => fn ($query) => $query->where('status', 'completed'),
                'fulfillments as recovery_required_count' => fn ($query) => $query->where('status', 'recovery_required'),
                'fulfillments as recovery_ready_count' => fn ($query) => $query->where('status', 'recovery_ready'),
                'fulfillments as executing_count' => fn ($query) => $query->where('status', 'executing'),
                'fulfillments as indeterminate_count' => fn ($query) => $query->where('status', 'provider_indeterminate'),
                'fulfillments as provider_ready_count' => fn ($query) => $query->where('status', 'awaiting_provider_dispatch'),
                'fulfillments as fallback_count' => fn ($query) => $query->where('status', 'fallback_planned'),
            ])
            ->latest('id')
            ->first()
            ?->only([
                'planned_count',
                'issued_count',
                'completed_count',
                'recovery_required_count',
                'recovery_ready_count',
                'executing_count',
                'indeterminate_count',
                'provider_ready_count',
                'fallback_count',
            ]) ?? [];
    }

    /** @return array<string, int|string|null> */
    private function authorizationFor(string $reference, mixed $owner): array
    {
        $authorization = $this->authorization($reference, $owner);

        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            return [];
        }

        return [
            'reference' => (string) $authorization->reference,
            'status' => (string) $authorization->status,
            'approval_pay_code' => $authorization->approval_pay_code,
            'beneficiary_count' => (int) $authorization->beneficiary_count,
            'principal_minor' => (int) $authorization->principal_minor,
            'manifest_hash' => (string) $authorization->manifest_hash,
            'instruction_blueprint_hash' => $authorization->instruction_blueprint_hash,
            'instruction_summary' => $this->instructionSummary(
                $authorization->instruction_blueprint_ciphertext ?? [],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>
     */
    private function instructionSummary(array $blueprint): array
    {
        return [
            'purpose' => data_get($blueprint, 'rider.message'),
            'has_link' => filled(data_get($blueprint, 'rider.url')),
            'has_splash' => filled(data_get($blueprint, 'rider.splash')),
            'input_fields' => data_get($blueprint, 'inputs.fields', []),
            'feedback_channels' => data_get($blueprint, 'feedback.channels', []),
            'validations' => array_keys(array_filter(
                data_get($blueprint, 'validation', []),
                fn (mixed $value): bool => data_get($value, 'required') === true,
            )),
            'onboarding_mode' => data_get($blueprint, 'claim.onboarding.mode', 'if_required'),
            'expiry_days' => (int) data_get($blueprint, 'expiry_days', 7),
        ];
    }

    /** @return array<int, array<string, int|string|null>> */
    private function fulfillmentsFor(string $reference, mixed $owner): array
    {
        $authorization = $this->authorization($reference, $owner);

        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            return [];
        }

        $fulfillments = $authorization->fulfillments()
            ->with('row')
            ->orderBy('id')
            ->limit(100)
            ->get();
        $payCodes = $fulfillments
            ->pluck('pay_code')
            ->filter(fn (mixed $code): bool => is_string($code) && trim($code) !== '')
            ->map(fn (string $code): string => trim($code))
            ->values();
        $claimStatusByCode = VoucherClaim::query()
            ->join('vouchers', 'vouchers.id', '=', 'voucher_claims.voucher_id')
            ->whereIn('vouchers.code', $payCodes)
            ->select(['vouchers.code', 'voucher_claims.status', 'voucher_claims.completed_at'])
            ->orderBy('voucher_claims.id')
            ->get()
            ->groupBy('code')
            ->map(fn ($claims) => [
                'status' => (string) $claims->last()->status,
                'completed_at' => $claims->last()->completed_at?->toIso8601String(),
            ]);
        $deliveryStatusByFulfillment = CampaignDeliveryAttempt::query()
            ->with('events')
            ->where('campaign_worksheet_authorization_id', $authorization->getKey())
            ->whereNotNull('campaign_worksheet_fulfillment_id')
            ->whereIn('campaign_worksheet_fulfillment_id', $fulfillments->pluck('id'))
            ->latest('id')
            ->get()
            ->groupBy('campaign_worksheet_fulfillment_id')
            ->map(function ($attempts): array {
                $attempt = $attempts->first();
                $lastEvent = $attempt?->events?->last();

                return [
                    'channel' => $attempt?->channel,
                    'status' => $lastEvent?->event_type ?? 'requested',
                    'safe_error_code' => $lastEvent?->safe_error_code,
                    'requested_at' => $attempt?->requested_at?->toIso8601String(),
                ];
            });

        return $fulfillments
            ->map(function (CampaignWorksheetFulfillment $fulfillment) use ($claimStatusByCode, $deliveryStatusByFulfillment): array {
                $beneficiary = $fulfillment->row?->beneficiary_ciphertext ?? [];
                $claim = $fulfillment->pay_code === null ? null : $claimStatusByCode->get($fulfillment->pay_code);
                $delivery = $deliveryStatusByFulfillment->get($fulfillment->getKey());

                return [
                    'reference' => (string) $fulfillment->reference,
                    'ordinal' => (int) ($fulfillment->row?->ordinal ?? 0),
                    'beneficiary' => (string) ($beneficiary['name'] ?? $beneficiary['mobile'] ?? $beneficiary['bank_account'] ?? 'Beneficiary'),
                    'amount_minor' => (int) ($fulfillment->row?->amount_minor ?? 0),
                    'mode' => (string) $fulfillment->mode,
                    'status' => (string) $fulfillment->status,
                    'monitor_label' => $this->fulfillmentMonitorLabel(
                        (string) $fulfillment->status,
                        is_array($claim) ? (string) ($claim['status'] ?? '') : null,
                        is_array($delivery) ? (string) ($delivery['status'] ?? '') : null,
                    ),
                    'provider_transfer_reference' => $fulfillment->provider_transfer_reference,
                    'pay_code' => $fulfillment->pay_code,
                    'claim_status' => is_array($claim) ? $claim['status'] : null,
                    'claim_completed_at' => is_array($claim) ? $claim['completed_at'] : null,
                    'delivery_status' => is_array($delivery) ? $delivery['status'] : null,
                    'delivery_channel' => is_array($delivery) ? $delivery['channel'] : null,
                    'delivery_requested_at' => is_array($delivery) ? $delivery['requested_at'] : null,
                    'delivery_safe_error_code' => is_array($delivery) ? $delivery['safe_error_code'] : null,
                ];
            })
            ->all();
    }

    private function fulfillmentMonitorLabel(
        string $status,
        ?string $claimStatus,
        ?string $deliveryStatus,
    ): string {
        if ($claimStatus === 'succeeded') {
            return 'Paid';
        }

        if ($status === 'completed') {
            return 'Paid';
        }

        if ($claimStatus === 'payout_retry_pending') {
            return 'Recovery claimed';
        }

        if ($deliveryStatus === 'completed') {
            return 'Recovery SMS sent';
        }

        if ($deliveryStatus === 'superseded') {
            return 'Recovery SMS superseded';
        }

        if ($deliveryStatus === 'queued' || $deliveryStatus === 'provider_queued') {
            return 'Recovery SMS queued';
        }

        return match ($status) {
            'planned' => 'Pending',
            'issued' => 'Pay Code issued',
            'executing' => 'Executing transfer',
            'provider_dispatched', 'awaiting_provider_dispatch' => 'Provider dispatched',
            'provider_indeterminate' => 'Provider review',
            'provider_dispatch_failed', 'recovery_required' => 'Recovery ready',
            'recovery_ready' => 'Recovery SMS ready',
            'fallback_planned' => 'Pay Code fallback planned',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function authorization(string $reference, mixed $owner): ?CampaignWorksheetAuthorization
    {
        $worksheet = CampaignWorksheet::query()
            ->where('reference', $reference)
            ->where('owner_type', $this->ownerType($owner))
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->first();

        return $worksheet instanceof CampaignWorksheet
            ? $worksheet->authorizations()->latest('id')->first()
            : null;
    }

    /** @return array<string, mixed> */
    private function deliveryFor(string $reference, mixed $owner): array
    {
        $authorization = $this->authorization($reference, $owner);

        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            return [
                'channels' => ['sms' => false, 'email' => false],
                'attempts' => [],
            ];
        }

        $latestBeneficiaryAttempts = [];
        $attempts = CampaignDeliveryAttempt::query()
            ->with(['events', 'fulfillment.row'])
            ->where('campaign_worksheet_authorization_id', $authorization->getKey())
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (CampaignDeliveryAttempt $attempt) use (&$latestBeneficiaryAttempts): array {
                $lastEvent = $attempt->events->last();
                $beneficiary = (array) ($attempt->fulfillment?->row?->beneficiary_ciphertext ?? []);
                $metadata = (array) $attempt->metadata;
                $purpose = (string) ($metadata['purpose'] ?? ($attempt->channel === 'export' ? 'beneficiary_export' : 'beneficiary_delivery'));
                $beneficiaryAttemptKey = $attempt->campaign_worksheet_fulfillment_id === null
                    ? null
                    : $attempt->campaign_worksheet_fulfillment_id.':'.$attempt->channel;
                $isLatestBeneficiaryAttempt = $beneficiaryAttemptKey !== null
                    && ! isset($latestBeneficiaryAttempts[$beneficiaryAttemptKey]);

                if ($beneficiaryAttemptKey !== null) {
                    $latestBeneficiaryAttempts[$beneficiaryAttemptKey] = true;
                }

                return [
                    'reference' => (string) $attempt->reference,
                    'channel' => (string) $attempt->channel,
                    'attempt_number' => (int) $attempt->attempt_number,
                    'retry_of_reference' => $attempt->retry_of_reference,
                    'purpose' => $purpose,
                    'beneficiary' => (string) ($beneficiary['name'] ?? ($purpose === 'officer_authorization' ? 'Approval officer' : 'Batch export')),
                    'pay_code' => $attempt->fulfillment?->pay_code,
                    'status' => (string) ($lastEvent?->event_type ?? 'requested'),
                    'safe_error_code' => $lastEvent?->safe_error_code,
                    'requested_at' => $attempt->requested_at?->toIso8601String(),
                    'can_retry' => in_array($lastEvent?->event_type, ['failed', 'blocked'], true)
                        && (bool) config("x-change.campaigns.delivery.{$attempt->channel}.enabled", false),
                    'can_resend' => $isLatestBeneficiaryAttempt
                        && $purpose === 'beneficiary_pay_code'
                        && $lastEvent?->event_type === 'completed'
                        && (bool) config("x-change.campaigns.delivery.{$attempt->channel}.enabled", false),
                ];
            })
            ->all();

        return [
            'channels' => [
                'sms' => (bool) config('x-change.campaigns.delivery.sms.enabled', false),
                'email' => (bool) config('x-change.campaigns.delivery.email.enabled', false),
            ],
            'attempts' => $attempts,
        ];
    }
}
