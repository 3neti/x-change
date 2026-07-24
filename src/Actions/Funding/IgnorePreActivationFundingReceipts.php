<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class IgnorePreActivationFundingReceipts
{
    public function __construct(
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(StandingFundingAddress $address): int
    {
        if ($address->activated_at === null) {
            return 0;
        }

        $ignored = DB::transaction(function () use ($address): int {
            $lockedAddress = StandingFundingAddress::query()
                ->lockForUpdate()
                ->findOrFail($address->getKey());
            $receipts = AccountFundingReceipt::query()
                ->where('standing_funding_address_id', $lockedAddress->getKey())
                ->where('observed_at', '<', $lockedAddress->activated_at)
                ->whereNotIn('status', [
                    AccountFundingReceiptStatus::Settled,
                    AccountFundingReceiptStatus::Reversed,
                    AccountFundingReceiptStatus::Ignored,
                ])
                ->whereNull('wallet_transaction_id')
                ->lockForUpdate()
                ->get();

            foreach ($receipts as $receipt) {
                $metadata = $receipt->metadata ?? [];
                $metadata['pre_activation_quarantine'] = [
                    'previous_status' => $receipt->status->value,
                    'previous_suspense_reason' => $receipt->suspense_reason,
                    'ignored_at' => now()->toRfc3339String(),
                ];

                $receipt->forceFill([
                    'status' => AccountFundingReceiptStatus::Ignored,
                    'suspense_reason' => 'pre_activation_transaction',
                    'metadata' => $metadata,
                ])->saveQuietly();

                FundingSuspenseCase::query()
                    ->where('provider_funding_observation_id', $receipt->provider_funding_observation_id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->get()
                    ->each(function (FundingSuspenseCase $case) use ($receipt): void {
                        $case->forceFill([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'resolved_by_type' => 'system',
                            'resolved_by_id' => 'standing-address-activation-boundary',
                            'resolution_code' => 'pre_activation_ignored',
                            'resolution' => [
                                'account_funding_receipt_reference' => $receipt->reference,
                                'balance_changed' => false,
                            ],
                        ])->saveQuietly();
                    });
            }

            return $receipts->count();
        }, attempts: 3);

        if ($ignored > 0) {
            $this->audit->log('funding.standing_address.pre_activation_receipts_ignored', [
                'standing_funding_address_reference' => $address->reference,
                'provider' => $address->provider_code,
                'ignored_count' => $ignored,
                'balance_changed' => false,
            ]);
        }

        return $ignored;
    }
}
