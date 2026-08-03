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
use LBHurtado\XChange\Models\VoucherClaim;
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
            'approval' => $approval,
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
            'instructions' => $this->instructionsArray($voucher),
            'claims' => $this->claimsArray($voucher),
            'redemption' => $this->redemptionSummary($voucher),
            'approval' => $approval,
        ];
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

        $amountMinor = $claim?->disbursed_amount_minor
            ?? ($reconciliation?->amount !== null
                ? (int) round((float) $reconciliation->amount * 100)
                : null)
            ?? data_get($voucher->metadata, 'treasury.pay_code_reservation.amount_minor');

        return [
            'status' => $reconciliation?->status ?? $claim?->status ?? 'redeemed',
            'amount_minor' => is_numeric($amountMinor) ? (int) $amountMinor : null,
            'currency' => $reconciliation?->currency ?? $claim?->currency ?? $this->currency($voucher),
            'provider' => $reconciliation?->provider,
            'settlement_rail' => $reconciliation?->settlement_rail,
            'bank_code' => $reconciliation?->bank_code ?? $claim?->bank_code,
            'account_number_masked' => $reconciliation?->account_number_masked
                ?? $claim?->account_number_masked,
            'provider_transaction_id' => $reconciliation?->provider_transaction_id,
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
        return $voucher->claims
            ->map(fn ($claim) => [
                'claim_number' => $claim->claim_number,
                'claim_type' => $claim->claim_type,
                'status' => $claim->status,
                'disbursed_amount_minor' => $claim->disbursed_amount_minor,
                'currency' => $claim->currency,
                'bank_code' => $claim->bank_code,
                'account_number_masked' => $claim->account_number_masked,
                'attempted_at' => $claim->attempted_at?->toIso8601String(),
                'completed_at' => $claim->completed_at?->toIso8601String(),
                'failure_message' => $claim->failure_message,
            ])
            ->values()
            ->all();
    }
}
