<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Funding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldConsumptionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\TransitionPayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;
use Throwable;

final class ResumeOnDemandPayCodeIssuanceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [5, 15, 45, 120];

    public function __construct(public readonly int $fundingOrderId) {}

    public function uniqueId(): string
    {
        return 'on-demand-pay-code-issuance:'.$this->fundingOrderId;
    }

    public function handle(
        TreasuryHoldOperationContract $holds,
        TreasuryAccountPortfolioProvisioningContract $portfolios,
        AccountBalanceReadModelContract $accountBalances,
        TransitionPayCodeIssuanceFundingOrder $transition,
        GeneratePayCode $generatePayCode,
    ): void {
        try {
            DB::transaction(function () use ($holds, $portfolios, $accountBalances, $transition, $generatePayCode): void {
                $order = PayCodeIssuanceFundingOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($this->fundingOrderId);

                if ($order->status === PayCodeIssuanceFundingOrderStatus::Issued) {
                    return;
                }

                if (data_get($order->metadata, 'provider_reversal') !== null) {
                    throw new RuntimeException(
                        'The issuance funding order is blocked by a provider reversal.',
                    );
                }

                if (! in_array($order->status, [
                    PayCodeIssuanceFundingOrderStatus::Funded,
                    PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
                ], true)
                    || $order->treasury_hold_reference === null
                    || $order->funded_at === null) {
                    throw new RuntimeException('The issuance funding order is not ready to issue.');
                }

                $issuer = $this->issuer($order);
                $positions = $portfolios->provision(
                    $issuer,
                    [$order->connection_reference],
                )->positions;
                $clientFunds = collect($positions)->first(
                    static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds,
                );

                if ($clientFunds === null) {
                    throw new RuntimeException('The Client Funds position required for issuance is unavailable.');
                }

                $order = $transition->handle(
                    order: $order,
                    status: PayCodeIssuanceFundingOrderStatus::Issuing,
                    eventType: 'issuance_started',
                    actorType: self::class,
                    actorId: (string) $this->fundingOrderId,
                    attributes: ['issuing_at' => now()],
                );
                $holds->consume(new TreasuryHoldConsumptionData(
                    operationReference: 'issuance-hold-consume:'.$order->reference,
                    holdReference: $order->treasury_hold_reference,
                    destinationPositionReference: $clientFunds->positionReference,
                    amountMinor: $order->required_amount_minor,
                    currency: $order->currency,
                    idempotencyKey: 'issuance-hold-consume-key:'.$order->reference,
                    externalReference: 'issuance-funding-order:'.$order->reference,
                ));

                $instructions = $order->instructions_ciphertext;

                if (method_exists($accountBalances, 'forget')) {
                    collect([
                        $order->provider,
                        data_get($instructions, 'provider'),
                    ])->filter()
                        ->unique()
                        ->each(fn (string $provider) => $accountBalances->forget(
                            $issuer,
                            $order->currency,
                            $provider,
                        ));
                }

                $issuanceProvider = (string) data_get($instructions, 'provider', $order->provider);
                $availableMinor = $accountBalances->providerBalanceMinor(
                    $issuer,
                    $issuanceProvider,
                    $order->currency,
                );

                if ($availableMinor === null || $availableMinor < $order->required_amount_minor) {
                    throw new RuntimeException(sprintf(
                        'The consumed issuance hold did not restore authoritative Client Funds [provider=%s; available=%s; required=%d].',
                        $issuanceProvider,
                        $availableMinor === null ? 'unavailable' : (string) $availableMinor,
                        $order->required_amount_minor,
                    ));
                }

                data_set($instructions, 'metadata.issuer_id', (string) $issuer->getKey());
                data_set($instructions, '_meta.issuer_type', $issuer::class);
                data_set($instructions, '_meta.on_demand_funding_order', $order->reference);
                $result = $generatePayCode->handle($instructions);

                $transition->handle(
                    order: $order,
                    status: PayCodeIssuanceFundingOrderStatus::Issued,
                    eventType: 'pay_code_issued',
                    actorType: self::class,
                    actorId: (string) $this->fundingOrderId,
                    attributes: [
                        'voucher_id' => $result->voucher_id,
                        'issued_at' => now(),
                    ],
                    metadata: ['pay_code' => $result->code],
                );
            }, attempts: 5);
        } catch (Throwable $exception) {
            $order = PayCodeIssuanceFundingOrder::query()->find($this->fundingOrderId);

            if ($order instanceof PayCodeIssuanceFundingOrder
                && in_array($order->status, [
                    PayCodeIssuanceFundingOrderStatus::Funded,
                    PayCodeIssuanceFundingOrderStatus::Issuing,
                ], true)) {
                $transition->handle(
                    order: $order,
                    status: PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
                    eventType: 'issuance_attention_required',
                    actorType: self::class,
                    actorId: (string) $this->fundingOrderId,
                    attributes: ['attention_at' => now()],
                    metadata: ['exception' => $exception::class],
                );
            }

            throw $exception;
        }
    }

    private function issuer(PayCodeIssuanceFundingOrder $order): Model
    {
        if (! class_exists($order->issuer_type)
            || ! is_a($order->issuer_type, Model::class, true)) {
            throw new RuntimeException('The issuance funding owner type is unavailable.');
        }

        $issuer = $order->issuer_type::query()->find($order->issuer_id);

        if (! $issuer instanceof Model) {
            throw new RuntimeException('The issuance funding owner is unavailable.');
        }

        return $issuer;
    }
}
