<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Bavix\Wallet\Exceptions\BalanceIsEmpty;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Actions\Funding\IssueTreasuryBackedPayCode;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class IssueCampaignWorksheetPayCodes
{
    public function __construct(
        private IssueTreasuryBackedPayCode $payCodes,
        private TreasuryPayCodeAccountingService $accounting,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    public function handle(string $authorizationReference, Model $owner, int $limit = 100): int
    {
        if ((string) auth()->id() !== (string) $owner->getKey()) {
            throw new RuntimeException('Campaign Pay Codes must be issued by the worksheet owner.');
        }

        if (! $owner instanceof Authenticatable) {
            throw new RuntimeException('Campaign Pay Code issuance requires an authenticatable worksheet owner.');
        }

        $authorization = CampaignWorksheetAuthorization::query()
            ->with(['worksheet', 'fulfillments.row'])
            ->where('reference', $authorizationReference)
            ->first();
        if (! $authorization instanceof CampaignWorksheetAuthorization || $authorization->status !== 'authorized' || $authorization->worksheet === null) {
            throw new RuntimeException('Campaign worksheet authorization is not ready for Pay Code issuance.');
        }

        $issued = 0;
        foreach ($authorization->fulfillments->filter(fn (CampaignWorksheetFulfillment $fulfillment): bool => (
            $fulfillment->mode === 'pay_code_distribution' && $fulfillment->status === 'planned'
        ) || $fulfillment->status === 'fallback_planned')->take(max(1, min($limit, 500))) as $fulfillment) {
            DB::transaction(function () use ($fulfillment, $authorization, $owner, &$issued): void {
                $locked = CampaignWorksheetFulfillment::query()->with('row')->lockForUpdate()->findOrFail($fulfillment->getKey());
                if (! in_array($locked->status, ['planned', 'fallback_planned'], true) || $locked->pay_code !== null || $locked->row === null) {
                    return;
                }

                $beneficiary = $locked->row->beneficiary_ciphertext;
                $connection = $this->connection(
                    (string) $locked->row->currency,
                );
                $voucher = $this->payCodes->handle($owner, [
                    'cash' => ['amount' => $locked->row->amount_minor / 100, 'currency' => $locked->row->currency, 'validation' => ['country' => 'PH', 'mobile' => $beneficiary['mobile'] ?? null]],
                    'inputs' => ['fields' => []], 'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null], 'rider' => ['message' => $authorization->worksheet?->name],
                    'count' => 1, 'prefix' => 'CAMP', 'mask' => '****', 'voucher_type' => VoucherType::REDEEMABLE->value,
                    'claim' => ['outcomes' => [['key' => 'provider_disbursement']], 'selection' => 'server', 'consumption' => 'one_of', 'default_outcome' => 'provider_disbursement', 'onboarding' => ['mode' => 'if_required'], 'claimant' => ['mode' => 'unbound'], 'profile' => 'voucher.claim.v1'],
                    'metadata' => ['flow_type' => 'campaign_fulfillment', 'issuer_id' => (string) $owner->getKey(), 'campaign' => ['authorization_reference' => $authorization->reference, 'fulfillment_reference' => $locked->reference, 'manifest_hash' => $authorization->manifest_hash]],
                ], now()->addDays($this->ttlDays()));
                $this->reservePrincipal(
                    owner: $owner,
                    voucher: $voucher,
                    connectionReference: $connection->reference,
                    amountMinor: (int) $locked->row->amount_minor,
                    currency: $connection->currency,
                );

                $locked->forceFill(['pay_code' => $voucher->code, 'status' => 'issued'])->save();
                $issued++;
            }, attempts: 5);
        }

        return $issued;
    }

    private function connection(string $currency): TreasuryProviderConnectionData
    {
        $reference = trim((string) config(
            'x-change.campaigns.pay_code_issuance.connection',
            'netbank-primary',
        ));
        $matches = collect($this->connections->active(
            $reference === '' ? [] : [$reference],
        ))->filter(
            static fn ($connection): bool => $connection->currency === mb_strtoupper(trim($currency)),
        )->values();

        if ($matches->count() !== 1) {
            throw new RuntimeException(
                'Campaign Pay Code issuance requires exactly one active Treasury connection for its currency.',
            );
        }

        return $matches->sole();
    }

    private function reservePrincipal(
        Model $owner,
        Voucher $voucher,
        string $connectionReference,
        int $amountMinor,
        string $currency,
    ): void {
        try {
            $this->accounting->reserve(
                accountOwner: $owner,
                voucher: $voucher,
                connectionReference: $connectionReference,
                providerPrincipalMinor: $amountMinor,
                currency: $currency,
            );
        } catch (BalanceIsEmpty|InsufficientFunds $exception) {
            throw new RuntimeException(
                'Campaign Pay Codes could not be issued because Client Funds are insufficient.',
                previous: $exception,
            );
        }
    }

    private function ttlDays(): int
    {
        return max(1, min(
            365,
            (int) config(
                'x-change.campaigns.pay_code_issuance.ttl_days',
                7,
            ),
        ));
    }
}
