<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\VoucherClaim;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordVoucherClaim
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $payload = [],
    ): VoucherClaim {
        $nextClaimNumber = (int) $voucher->claims()->count() + 1;

        $requestedAmount = $result->requested_amount;
        $disbursedAmount = $result->disbursed_amount;
        $remainingBalance = $result->remaining_balance;

        $bankCode = data_get($payload, 'bank_account.bank_code', data_get($payload, 'bank_code'));
        $accountNumber = data_get($payload, 'bank_account.account_number', data_get($payload, 'account_number'));

        $claim = VoucherClaim::query()->create([
            'voucher_id' => $voucher->getKey(),
            'claim_number' => $nextClaimNumber,
            'claim_type' => (string) ($result->claim_type ?: 'claim'),
            'status' => $this->normalizeStatus((string) $result->status),
            'requested_amount_minor' => $this->toMinorUnits($requestedAmount),
            'disbursed_amount_minor' => $this->toMinorUnits($disbursedAmount),
            'remaining_balance_minor' => $this->toMinorUnits($remainingBalance),
            'currency' => $result->currency ?: 'PHP',
            'claimer_mobile' => data_get($payload, 'mobile'),
            'recipient_country' => data_get($payload, 'recipient_country', data_get($payload, 'country')),
            'bank_code' => $bankCode,
            'account_number_masked' => $this->maskAccountNumber($accountNumber),
            'idempotency_key' => data_get($payload, '_meta.idempotency_key'),
            'reference' => data_get($payload, 'reference'),
            'attempted_at' => now(),
            'completed_at' => $this->shouldSetCompletedAt((string) $result->status) ? now() : null,
            'failure_message' => $this->resolveFailureMessage($result),
            'meta' => [
                'messages' => $result->messages,
                'disbursement' => $result->disbursement,
                'fully_claimed' => $result->fully_claimed,
                'named_slices' => [
                    'selected_ids' => data_get($payload, 'slice_ids', []),
                    'selected' => data_get($payload, '_named_slices.selected', []),
                ],
            ],
        ]);

        $this->markVoucherRedeemedWhenFullyClaimed($voucher, $result);

        return $claim;
    }

    protected function toMinorUnits(?float $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        return (int) round($amount * 100);
    }

    protected function normalizeStatus(string $status): string
    {
        return match ($status) {
            'success', 'completed' => 'succeeded',
            'review', 'pending_review' => 'pending_review',
            'failed', 'error' => 'failed',
            default => $status !== '' ? $status : 'pending',
        };
    }

    protected function shouldSetCompletedAt(string $status): bool
    {
        return in_array(
            $this->normalizeStatus($status),
            ['succeeded', 'failed', 'pending_review', 'redeemed', 'withdrawn'],
            true
        );
    }

    protected function resolveFailureMessage(SubmitPayCodeClaimResultData $result): ?string
    {
        if (in_array($this->normalizeStatus((string) $result->status), ['failed', 'pending_review'], true)) {
            $messages = $result->messages ?? [];

            if (is_array($messages) && $messages !== []) {
                return implode(' | ', array_map(
                    static fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
                    $messages
                ));
            }

            return 'Claim failed or requires review.';
        }

        return null;
    }

    protected function markVoucherRedeemedWhenFullyClaimed(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
    ): void {
        if (! $this->shouldMarkVoucherRedeemed($result)) {
            return;
        }

        if ($voucher->redeemed_at !== null) {
            return;
        }

        $voucher->forceFill([
            'redeemed_at' => now(),
        ])->save();
    }

    protected function shouldMarkVoucherRedeemed(SubmitPayCodeClaimResultData $result): bool
    {
        if (! $result->fully_claimed || ! $result->claimed) {
            return false;
        }

        return in_array($this->normalizeStatus((string) $result->status), [
            'succeeded',
            'success',
            'completed',
            'redeemed',
            'withdrawn',
        ], true);
    }

    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)).substr($accountNumber, -4);
    }
}
