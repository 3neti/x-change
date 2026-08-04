<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Brick\Money\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\VoucherOperationalSummaryData;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Treasury\ReleasePayCodeTerminalReserve;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PayoutDestinationRevision;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;
use RuntimeException;

class VoucherLifecycleService implements VoucherLifecycleServiceContract
{
    public function __construct(
        protected VoucherAccessContract $vouchers,
        protected ?ClaimApprovalStatusResolver $approvalStatus = null,
        protected ?NamedVoucherSliceService $namedSlices = null,
        protected ?ReleasePayCodeTerminalReserve $terminalReleases = null,
    ) {}

    public function list(array $filters = []): array
    {
        $items = $this->vouchers->list($filters);

        return collect($items)
            ->map(fn (Voucher $voucher) => $this->toSummaryArray($voucher))
            ->values()
            ->all();
    }

    public function show(string $voucher): mixed
    {
        $model = $this->vouchers->findOrFail($voucher);

        return $this->toDetailArray($model);
    }

    public function showByCode(string $code): mixed
    {
        $model = $this->vouchers->findByCodeOrFail(strtoupper(trim($code)));

        return $this->toDetailArray($model);
    }

    public function status(string $voucher): mixed
    {
        $model = $this->vouchers->findOrFail($voucher);

        return $this->toStatusArray($model);
    }

    public function cancel(string $voucher, array $payload = []): mixed
    {
        $model = $this->vouchers->findOrFail($voucher);

        return DB::transaction(function () use ($model, $payload): array {
            $locked = Voucher::query()
                ->with('owner')
                ->lockForUpdate()
                ->findOrFail($model->getKey());

            $this->authorizeCancellation($locked);
            $this->assertUnclaimed($locked);

            $release = $this->terminalReleaseAction()->handle(
                $locked,
                'cancelled',
            );

            $locked->state = VoucherState::CLOSED;
            $locked->closed_at ??= now();
            $locked->save();

            return [
                'voucher_id' => $locked->id,
                'code' => $locked->code,
                'status' => 'cancelled',
                'cancelled' => true,
                'reason' => $payload['reason'] ?? null,
                'treasury_release' => $release->toArray(),
                'messages' => ['Voucher cancelled successfully.'],
            ];
        }, attempts: 5);
    }

    /**
     * @throws AuthorizationException
     */
    protected function authorizeCancellation(Voucher $voucher): void
    {
        $actor = Auth::user();

        if (! $actor instanceof Model) {
            return;
        }

        $owner = $voucher->owner;

        if (! $owner instanceof Model || ! $owner->is($actor)) {
            throw new AuthorizationException(
                'Only the Pay Code owner may cancel it.',
            );
        }
    }

    protected function assertUnclaimed(Voucher $voucher): void
    {
        $claimed = $voucher->redeemed_at !== null
            || VoucherClaim::query()
                ->where('voucher_id', $voucher->getKey())
                ->exists()
            || DisbursementReconciliation::query()
                ->where('voucher_id', $voucher->getKey())
                ->exists();

        if ($claimed) {
            throw new RuntimeException(
                "Claimed Pay Code [{$voucher->code}] cannot be cancelled or released.",
            );
        }
    }

    protected function terminalReleaseAction(): ReleasePayCodeTerminalReserve
    {
        return $this->terminalReleases
            ?? app(ReleasePayCodeTerminalReserve::class);
    }

    protected function toSummaryArray(Voucher $voucher): array
    {
        $status = $this->statusLabel($voucher);
        $approval = $this->approvalSummary($voucher);
        $operational = $this->operationalSummary($voucher);

        return [
            'id' => $voucher->id,
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'amount' => $this->amount($voucher),
            'currency' => $this->currency($voucher),
            'status' => $status,
            'display_status' => $this->displayStatus($status, $approval),
            'issuer_id' => $this->issuerId($voucher),
            'capability' => [
                'key' => $operational->capability_key,
                'label' => $operational->capability_label,
                'voucher_type_label' => $operational->voucher_type_label,
            ],
            'instruction_badges' => $operational->instruction_badges,
            'party' => $this->partySummary($voucher),
            'timing' => [
                'created_at' => $voucher->created_at?->toIso8601String(),
                'starts_at' => $voucher->starts_at?->toIso8601String(),
                'expires_at' => $voucher->expires_at?->toIso8601String(),
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
            ],
            'attention' => $this->payoutAttention($voucher),
            'approval' => $approval,
        ];
    }

    /**
     * @return array{key: string, label: string, message: string, tone: string}|null
     */
    protected function payoutAttention(Voucher $voucher): ?array
    {
        if (data_get($voucher->metadata, 'disbursement.requires_recovery') !== true) {
            return null;
        }

        $reason = $this->nullableDisplayValue(
            data_get($voucher->metadata, 'disbursement.rejection_reason'),
        ) ?? 'The receiving institution rejected the payout destination.';

        return [
            'key' => 'payout_rejected',
            'label' => 'Payout rejected',
            'message' => $reason,
            'tone' => 'critical',
        ];
    }

    protected function operationalSummary(Voucher $voucher): VoucherOperationalSummaryData
    {
        try {
            return VoucherOperationalSummaryData::fromInstructions(
                $voucher->instructions,
                $voucher->voucher_type,
            );
        } catch (\Throwable) {
            return new VoucherOperationalSummaryData(
                capability_key: 'disbursement',
                capability_label: 'Disbursement',
                voucher_type_label: 'Redeemable',
                instruction_badges: [],
            );
        }
    }

    /**
     * @return array{state: string, label: string, primary: string, secondary: string|null, masked: bool}
     */
    protected function partySummary(Voucher $voucher): array
    {
        $redeemer = $this->redeemer($voucher);

        if ($redeemer instanceof Model) {
            $name = $this->nullableDisplayValue($redeemer->getAttribute('name'));
            $mobile = $this->maskedMobile($redeemer->getAttribute('mobile'));

            return [
                'state' => 'claimed',
                'label' => 'Claimed by',
                'primary' => $name ?? $mobile ?? 'Contact unavailable',
                'secondary' => $name !== null ? $mobile : null,
                'masked' => $mobile !== null,
            ];
        }

        try {
            $instructions = $voucher->instructions;
            $vendorAlias = $this->nullableDisplayValue($instructions->cash->validation->payable);
            $targetMobile = $this->maskedMobile($instructions->cash->validation->mobile);
        } catch (\Throwable) {
            $vendorAlias = null;
            $targetMobile = null;
        }

        if ($vendorAlias !== null) {
            return [
                'state' => 'targeted',
                'label' => 'Vendor',
                'primary' => Str::limit($vendorAlias, 40, '…'),
                'secondary' => null,
                'masked' => false,
            ];
        }

        if ($targetMobile !== null) {
            return [
                'state' => 'targeted',
                'label' => 'For',
                'primary' => $targetMobile,
                'secondary' => null,
                'masked' => true,
            ];
        }

        return [
            'state' => 'open',
            'label' => 'Availability',
            'primary' => 'Open claim',
            'secondary' => null,
            'masked' => false,
        ];
    }

    protected function redeemer(Voucher $voucher): ?Model
    {
        if (! $voucher->relationLoaded('redeemers')) {
            return null;
        }

        $redemption = $voucher->redeemers->first();

        if (
            ! $redemption instanceof Model
            || ! $redemption->relationLoaded('redeemer')
        ) {
            return null;
        }

        $redeemer = $redemption->getRelation('redeemer');

        return $redeemer instanceof Model ? $redeemer : null;
    }

    protected function maskedMobile(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if (! is_string($digits) || strlen($digits) < 4) {
            return null;
        }

        return '•••• '.substr($digits, -4);
    }

    protected function nullableDisplayValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function toDetailArray(Voucher $voucher): array
    {
        $status = $this->statusLabel($voucher);
        $approval = $this->approvalSummary($voucher);
        $operational = $this->operationalSummary($voucher);

        return [
            'id' => $voucher->id,
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'amount' => $this->amount($voucher),
            'currency' => $this->currency($voucher),
            'status' => $status,
            'display_status' => $this->displayStatus($status, $approval),
            'issuer_id' => $this->issuerId($voucher),
            'claimed' => $this->isClaimed($voucher),
            'fully_claimed' => $this->isFullyClaimed($voucher),
            'created_at' => $voucher->created_at?->toIso8601String(),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'starts_at' => $voucher->starts_at?->toIso8601String(),
            'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
            'capability' => [
                'key' => $operational->capability_key,
                'label' => $operational->capability_label,
                'voucher_type_label' => $operational->voucher_type_label,
            ],
            'party' => $this->partySummary($voucher),
            'amounts' => $this->amountFacts($voucher),
            'instructions' => $this->instructionsArray($voucher),
            'claims' => $this->claimsArray($voucher),
            'claim_evidence' => $this->claimEvidenceArray($voucher),
            'redemption' => $this->redemptionSummary($voucher),
            'backing' => $this->backingSummary($voucher),
            'settlement_envelope' => $this->settlementEnvelopeSummary($voucher),
            'approval' => $approval,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function amountFacts(Voucher $voucher): array
    {
        $instructions = $this->instructionsArray($voucher) ?? [];
        $currency = $this->currency($voucher);
        $faceAmountMinor = (int) round($this->amount($voucher) * 100);
        $targetAmount = data_get($voucher, 'target_amount')
            ?? data_get($instructions, 'target_amount');
        $targetAmountMinor = is_numeric($targetAmount)
            ? (int) round((float) $targetAmount * 100)
            : null;
        $reservedAmountMinor = data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation.amount_minor',
        );
        $disbursedAmountMinor = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->sum('disbursed_amount_minor');
        $latestRemaining = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->value('remaining_balance_minor');

        return collect([
            [
                'key' => 'face_value',
                'label' => 'Pay Code Value',
                'amount_minor' => $faceAmountMinor,
                'currency' => $currency,
                'authority' => 'voucher_instructions',
                'primary' => $reservedAmountMinor === null && $targetAmountMinor === null,
            ],
            is_numeric($targetAmountMinor) ? [
                'key' => 'target_value',
                'label' => 'Target Value',
                'amount_minor' => (int) $targetAmountMinor,
                'currency' => $currency,
                'authority' => 'settlement_target',
                'primary' => $reservedAmountMinor === null,
            ] : null,
            is_numeric($reservedAmountMinor) ? [
                'key' => 'reserved_principal',
                'label' => 'Reserved Principal',
                'amount_minor' => (int) $reservedAmountMinor,
                'currency' => (string) data_get(
                    $voucher->metadata,
                    'treasury.pay_code_reservation.currency',
                    $currency,
                ),
                'authority' => 'treasury_position',
                'primary' => true,
            ] : null,
            $disbursedAmountMinor > 0 ? [
                'key' => 'paid_amount',
                'label' => 'Paid Amount',
                'amount_minor' => (int) $disbursedAmountMinor,
                'currency' => $currency,
                'authority' => 'voucher_claims',
                'primary' => false,
            ] : null,
            is_numeric($latestRemaining) ? [
                'key' => 'remaining_value',
                'label' => 'Remaining Value',
                'amount_minor' => (int) $latestRemaining,
                'currency' => $currency,
                'authority' => 'latest_claim',
                'primary' => false,
            ] : null,
        ])->filter()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function backingSummary(Voucher $voucher): array
    {
        $reservation = data_get($voucher->metadata, 'treasury.pay_code_reservation');

        if (is_array($reservation) && $reservation !== []) {
            return [
                'mode' => 'treasury_position',
                'label' => 'Treasury Backing',
                'status' => (string) ($reservation['status'] ?? 'reserved'),
                'amount_minor' => is_numeric($reservation['amount_minor'] ?? null)
                    ? (int) $reservation['amount_minor']
                    : null,
                'currency' => $reservation['currency'] ?? $this->currency($voucher),
                'provider' => $reservation['provider'] ?? null,
                'connection_reference' => $reservation['connection_reference'] ?? null,
                'operation_reference' => $reservation['operation_reference'] ?? null,
                'cash_entity_present' => $voucher->cash !== null,
                'provider_calls_on_read' => false,
            ];
        }

        if ($voucher->cash !== null) {
            return [
                'mode' => 'legacy_cash_entity',
                'label' => 'Legacy Backing',
                'status' => 'cash_entity',
                'amount_minor' => (int) round($this->amount($voucher) * 100),
                'currency' => $this->currency($voucher),
                'cash_entity_present' => true,
                'provider_calls_on_read' => false,
            ];
        }

        return [
            'mode' => 'unverified',
            'label' => 'Backing Unverified',
            'status' => 'not_available',
            'amount_minor' => null,
            'currency' => $this->currency($voucher),
            'cash_entity_present' => false,
            'provider_calls_on_read' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function claimEvidenceArray(Voucher $voucher): array
    {
        $evidence = VoucherClaimEvidence::query()
            ->with('claim:id,claim_number')
            ->where('voucher_id', $voucher->getKey())
            ->oldest('id')
            ->get();

        if ($evidence->isNotEmpty()) {
            return $evidence->map(function (VoucherClaimEvidence $item) use ($voucher): array {
                $revealable = filled($item->artifact_path);

                return [
                    'id' => (int) $item->getKey(),
                    'claim_id' => (int) $item->voucher_claim_id,
                    'claim_number' => $item->claim?->claim_number,
                    'key' => $item->requirement_key,
                    'label' => Str::headline($item->requirement_key),
                    'group' => $this->evidenceGroup($item->requirement_key),
                    'kind' => $item->kind->value,
                    'status' => $item->status->value,
                    'value' => $item->summary,
                    'captured_at' => $item->captured_at?->toIso8601String(),
                    'verified_at' => $item->verified_at?->toIso8601String(),
                    'revealable' => $revealable,
                    'reveal_href' => $revealable
                        && Route::has('x-change.cockpit.pay-codes.evidence.show')
                            ? route('x-change.cockpit.pay-codes.evidence.show', [
                                'code' => $voucher->code,
                                'source' => 'claim',
                                'evidence' => $item->getKey(),
                            ])
                            : null,
                    'legacy' => false,
                ];
            })->values()->all();
        }

        return $voucher->inputs()
            ->oldest('id')
            ->get()
            ->map(function (Model $input) use ($voucher): array {
                $name = (string) $input->getAttribute('name');
                $value = $this->decodeEvidenceValue($input->getAttribute('value'));
                $kind = $this->evidenceKind($name, $value);
                $sensitive = in_array($name, [
                    'otp',
                    'secret',
                    'signature',
                    'selfie',
                    'kyc',
                    'kyc_id_front',
                    'kyc_id_back',
                ], true);

                $revealable = in_array($kind, ['image', 'document'], true)
                    || ($kind === 'location' && filled(data_get($value, 'map')));

                return [
                    'id' => (int) $input->getKey(),
                    'claim_id' => null,
                    'claim_number' => null,
                    'key' => $name,
                    'label' => Str::headline($name),
                    'group' => $this->evidenceGroup($name),
                    'kind' => $kind,
                    'status' => 'captured',
                    'value' => $sensitive
                        ? null
                        : $this->safeEvidenceValue($name, $value),
                    'revealable' => $revealable,
                    'reveal_href' => $revealable
                        && Route::has('x-change.cockpit.pay-codes.evidence.show')
                            ? route('x-change.cockpit.pay-codes.evidence.show', [
                                'code' => $voucher->code,
                                'source' => 'input',
                                'evidence' => $input->getKey(),
                            ])
                            : null,
                    'legacy' => true,
                ];
            })
            ->values()
            ->all();
    }

    protected function evidenceGroup(string $name): string
    {
        return match (true) {
            in_array($name, ['name', 'birth_date'], true) => 'identity',
            in_array($name, ['mobile', 'email', 'address'], true) => 'contact',
            $name === 'location' => 'location',
            in_array($name, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back'], true) => 'media',
            $name === 'kyc' || str_starts_with($name, 'kyc_') || str_starts_with($name, 'otp') => 'verification',
            default => 'other',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function settlementEnvelopeSummary(Voucher $voucher): array
    {
        $envelope = $voucher->envelope()
            ->with(['checklistItems', 'attachments', 'signals'])
            ->first();

        if ($envelope === null) {
            return [
                'available' => false,
                'required' => false,
                'status' => 'not_attached',
            ];
        }

        return [
            'available' => true,
            'required' => true,
            'reference' => $envelope->reference_code,
            'driver' => $envelope->driver_id,
            'driver_version' => $envelope->driver_version,
            'status' => $envelope->status->value,
            'payload_version' => $envelope->payload_version,
            'settleable' => $envelope->isSettleable(),
            'checklist' => $envelope->getChecklistStatus(),
            'gates' => collect($envelope->gates_cache ?? [])
                ->map(fn (mixed $value, mixed $key): array => [
                    'key' => (string) $key,
                    'label' => Str::headline((string) $key),
                    'satisfied' => $value === true,
                ])->values()->all(),
            'signals' => $envelope->signals->map(fn (Model $signal): array => [
                'key' => (string) $signal->getAttribute('key'),
                'label' => Str::headline((string) $signal->getAttribute('key')),
                'source' => $signal->getAttribute('source'),
                'present' => filled($signal->getAttribute('value')),
            ])->values()->all(),
            'attachments' => $envelope->attachments->map(fn (Model $attachment): array => [
                'id' => (int) $attachment->getKey(),
                'label' => (string) $attachment->getAttribute('original_filename'),
                'document_type' => (string) $attachment->getAttribute('doc_type'),
                'mime_type' => (string) $attachment->getAttribute('mime_type'),
                'size' => (int) $attachment->getAttribute('size'),
                'review_status' => (string) $attachment->getAttribute('review_status'),
                'reveal_href' => Route::has('x-change.cockpit.pay-codes.evidence.show')
                    ? route('x-change.cockpit.pay-codes.evidence.show', [
                        'code' => $voucher->code,
                        'source' => 'envelope',
                        'evidence' => $attachment->getKey(),
                    ])
                    : null,
            ])->values()->all(),
            'timestamps' => [
                'created_at' => $envelope->created_at?->toIso8601String(),
                'locked_at' => $envelope->locked_at?->toIso8601String(),
                'settled_at' => $envelope->settled_at?->toIso8601String(),
                'cancelled_at' => $envelope->cancelled_at?->toIso8601String(),
            ],
            'payload_exposed' => false,
        ];
    }

    protected function evidenceKind(string $name, mixed $value): string
    {
        if (in_array($name, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back'], true)) {
            return 'image';
        }

        if ($name === 'location') {
            return 'location';
        }

        if ($name === 'kyc' || str_starts_with($name, 'kyc_')) {
            return 'verification';
        }

        return 'text';
    }

    protected function safeEvidenceValue(string $name, mixed $value): ?string
    {
        if ($name === 'location' && is_array($value)) {
            $address = data_get($value, 'formatted_address');

            if (is_scalar($address) && trim((string) $address) !== '') {
                return Str::limit(trim((string) $address), 240);
            }

            $latitude = data_get($value, 'latitude');
            $longitude = data_get($value, 'longitude');

            return is_numeric($latitude) && is_numeric($longitude)
                ? sprintf('%.6f, %.6f', (float) $latitude, (float) $longitude)
                : 'Location captured';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if ($name === 'mobile') {
            return $this->maskedMobile($normalized);
        }

        if ($name === 'email') {
            [$local, $domain] = array_pad(explode('@', $normalized, 2), 2, null);

            return $domain === null
                ? 'Redacted'
                : Str::substr($local, 0, 1).'•••@'.$domain;
        }

        return Str::limit($normalized, 240);
    }

    protected function decodeEvidenceValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : $value;
    }

    protected function toStatusArray(Voucher $voucher): array
    {
        $claimed = $this->isClaimed($voucher);
        $fullyClaimed = $this->isFullyClaimed($voucher);
        $amount = $this->amount($voucher);

        return [
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'status' => $this->statusLabel($voucher),
            'claimed' => $claimed,
            'fully_claimed' => $fullyClaimed,
            'remaining_balance' => $fullyClaimed ? 0.0 : $amount,
            'currency' => $this->currency($voucher),
        ];
    }

    protected function amount(Voucher $voucher): float
    {
        $amount = data_get($voucher, 'cash.amount');

        if ($amount instanceof Money) {
            return $amount->getAmount()->toFloat();
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        $instructionAmount = data_get($this->instructionsArray($voucher), 'cash.amount');

        if (is_numeric($instructionAmount)) {
            return (float) $instructionAmount;
        }

        return 0.0;
    }

    protected function currency(Voucher $voucher): string
    {
        $currency = data_get($voucher, 'cash.currency');

        if (is_string($currency) && $currency !== '') {
            return $currency;
        }

        $amount = data_get($voucher, 'cash.amount');

        if ($amount instanceof Money) {
            return $amount->getCurrency()->getCurrencyCode();
        }

        $instructionCurrency = data_get($this->instructionsArray($voucher), 'cash.currency');

        if (is_string($instructionCurrency) && $instructionCurrency !== '') {
            return $instructionCurrency;
        }

        return 'PHP';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function redemptionSummary(Voucher $voucher): ?array
    {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();
        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();

        if (! $claim instanceof VoucherClaim && ! $reconciliation instanceof DisbursementReconciliation) {
            return null;
        }

        $revision = PayoutDestinationRevision::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('version')
            ->first();
        $amountMinor = ($reconciliation?->amount !== null
                ? (int) round((float) $reconciliation->amount * 100)
                : null)
            ?? $claim?->requested_amount_minor
            ?? $claim?->disbursed_amount_minor
            ?? data_get($voucher->metadata, 'treasury.pay_code_reservation.amount_minor');
        $requiresRecovery = data_get($voucher->metadata, 'disbursement.requires_recovery') === true;

        return [
            'status' => $reconciliation?->status ?? $claim?->status ?? 'redeemed',
            'claim_status' => $claim?->status ?? 'redeemed',
            'payout_status' => $reconciliation?->status,
            'amount_minor' => is_numeric($amountMinor) ? (int) $amountMinor : null,
            'currency' => $reconciliation?->currency ?? $claim?->currency ?? $this->currency($voucher),
            'provider' => $reconciliation?->provider,
            'settlement_rail' => $reconciliation?->settlement_rail,
            'bank_code' => $reconciliation?->bank_code ?? $claim?->bank_code,
            'account_number_masked' => $reconciliation?->account_number_masked
                ?? $claim?->account_number_masked,
            'provider_transaction_id' => $reconciliation?->provider_transaction_id,
            'rejection_reason' => $reconciliation?->error_message,
            'requires_recovery' => $requiresRecovery,
            'can_correct_destination' => $requiresRecovery
                && data_get($voucher->metadata, 'treasury.pay_code_reservation.status') === 'recovery_pending'
                && $claim?->status === 'payout_rejected',
            'destination_revision' => $revision instanceof PayoutDestinationRevision ? [
                'reference' => $revision->reference,
                'version' => $revision->version,
                'bank_code' => $revision->bank_code,
                'account_number_masked' => $revision->account_number_masked,
                'validation_status' => $revision->validation_status,
                'validation_message' => data_get($revision->validation_metadata, 'message'),
                'recorded_at' => $revision->recorded_at?->toIso8601String(),
            ] : null,
            'completed_at' => $reconciliation?->completed_at?->toIso8601String()
                ?? $claim?->completed_at?->toIso8601String(),
        ];
    }

    protected function issuerId(Voucher $voucher): ?int
    {
        $ownerKey = $voucher->owner?->getKey();

        return $ownerKey !== null ? (int) $ownerKey : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function approvalSummary(Voucher $voucher): ?array
    {
        $approval = ($this->approvalStatus ?? app(ClaimApprovalStatusResolver::class))
            ->resolve($voucher);

        if ($approval === null || ! $approval->otp_required) {
            return null;
        }

        return [
            'required' => true,
            'type' => 'otp',
            'provider' => $approval->provider,
            'reference_id' => $approval->reference_id,
            'message' => $approval->message,
            'action_url' => $this->approvalUrl($voucher),
        ];
    }

    protected function approvalUrl(Voucher $voucher): string
    {
        if (Route::has('x-change.pay-codes.approval')) {
            return route('x-change.pay-codes.approval', [
                'code' => $voucher->code,
            ]);
        }

        return '/x/pay-codes/'.$voucher->code.'/approval';
    }

    /**
     * @param  array<string, mixed>|null  $approval
     */
    protected function displayStatus(string $status, ?array $approval): string
    {
        if (($approval['required'] ?? false) === true) {
            return 'awaiting_approval';
        }

        return $status;
    }

    protected function statusLabel(Voucher $voucher): string
    {
        if ($voucher->isClosed()) {
            return 'cancelled';
        }

        if ($voucher->isExpired()) {
            return 'expired';
        }

        if ($this->isFullyClaimed($voucher)) {
            return 'redeemed';
        }

        return strtolower((string) $voucher->state->value);
    }

    protected function isClaimed(Voucher $voucher): bool
    {
        return $voucher->redeemed_at !== null
            || $voucher->claims()->exists();
    }

    protected function isFullyClaimed(Voucher $voucher): bool
    {
        if ($this->namedSlices()->hasNamedSlices($voucher)) {
            return $this->namedSlices()->allSlicesClaimed($voucher);
        }

        if ($voucher->redeemed_at !== null) {
            return true;
        }

        $latestClaim = $voucher->claims()
            ->reorder()
            ->orderByDesc('claim_number')
            ->first();

        if ($latestClaim === null) {
            return false;
        }

        if ((bool) data_get($latestClaim->meta, 'fully_claimed') === true) {
            return true;
        }

        return $latestClaim->remaining_balance_minor !== null
            && (int) $latestClaim->remaining_balance_minor <= 0;
    }

    protected function namedSlices(): NamedVoucherSliceService
    {
        return $this->namedSlices ??= app(NamedVoucherSliceService::class);
    }

    protected function instructionsArray(Voucher $voucher): ?array
    {
        try {
            $instructions = $voucher->instructions;

            return $instructions ? $instructions->toArray() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function claimsArray(Voucher $voucher): array
    {
        $requiredCount = count(app(ClaimEvidenceRequirements::class)->forVoucher($voucher));

        return $voucher->claims()
            ->withCount('evidence')
            ->oldest('claim_number')
            ->get()
            ->map(fn ($claim) => [
                'id' => (int) $claim->getKey(),
                'claim_number' => $claim->claim_number,
                'claim_type' => $claim->claim_type,
                'settlement_mode' => $claim->settlement_mode,
                'status' => $claim->status,
                'requested_amount_minor' => $claim->requested_amount_minor,
                'disbursed_amount_minor' => $claim->disbursed_amount_minor,
                'remaining_balance_minor' => $claim->remaining_balance_minor,
                'currency' => $claim->currency,
                'claimer_mobile_masked' => $this->maskedMobile($claim->claimer_mobile),
                'bank_code' => $claim->bank_code,
                'account_number_masked' => $claim->account_number_masked,
                'reference' => $claim->reference,
                'attempted_at' => $claim->attempted_at?->toIso8601String(),
                'completed_at' => $claim->completed_at?->toIso8601String(),
                'failure_message' => $claim->failure_message,
                'evidence' => [
                    'required_count' => $requiredCount,
                    'captured_count' => (int) $claim->evidence_count,
                    'complete' => $requiredCount === 0
                        || (int) $claim->evidence_count >= $requiredCount,
                    'manifest_version' => data_get($claim->meta, 'evidence.manifest_version'),
                ],
            ])
            ->values()
            ->all();
    }
}
