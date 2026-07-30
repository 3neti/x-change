<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Bavix\Wallet\Exceptions\BalanceIsEmpty;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Actions\Funding\IssueTreasuryBackedPayCode;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Services\Campaigns\CampaignVoucherInstructionCompiler;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class IssueCampaignWorksheetPayCodes
{
    public function __construct(
        private IssueTreasuryBackedPayCode $payCodes,
        private TreasuryPayCodeAccountingService $accounting,
        private TreasuryProviderConnectionCatalog $connections,
        private CampaignVoucherInstructionCompiler $instructionCompiler,
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

                $connection = $this->connection(
                    (string) $locked->row->currency,
                );
                $voucher = $this->payCodes->handle(
                    $owner,
                    $this->instructionCompiler->compile($authorization, $locked, $owner),
                    now()->addDays($this->ttlDays($authorization)),
                );
                $this->reservePrincipal(
                    owner: $owner,
                    voucher: $voucher,
                    connectionReference: $connection->reference,
                    amountMinor: (int) $locked->row->amount_minor,
                    currency: $connection->currency,
                );

                $locked->forceFill([
                    'pay_code' => $voucher->code,
                    'status' => 'issued',
                    'metadata' => array_replace(
                        $locked->metadata ?? [],
                        [
                            'instruction_blueprint_hash' => $authorization->instruction_blueprint_hash,
                            'voucher_instruction_schema' => 'voucher.instructions.v1',
                        ],
                    ),
                ])->save();
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

    private function ttlDays(CampaignWorksheetAuthorization $authorization): int
    {
        return max(1, min(
            365,
            (int) data_get(
                $authorization->instruction_blueprint_ciphertext,
                'expiry_days',
                config('x-change.campaigns.pay_code_issuance.ttl_days', 7),
            ),
        ));
    }
}
