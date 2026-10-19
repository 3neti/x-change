<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Bavix\Wallet\Interfaces\ProductInterface;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transfer;
use LBHurtado\Wallet\Contracts\ProductWalletTransferContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionWalletResolverContract;
use LBHurtado\XChange\Contracts\CommercialProductResolverContract;
use LBHurtado\XChange\Contracts\CommercialProductRevenueMovementContract;
use RuntimeException;

final readonly class BavixCommercialProductRevenueMovement implements CommercialProductRevenueMovementContract
{
    public function __construct(
        private TreasuryPositionWalletResolverContract $positions,
        private CommercialProductResolverContract $products,
        private ProductWalletTransferContract $productTransfers,
    ) {}

    public function post(array $transfer): array
    {
        $source = $this->source($transfer);
        $product = $this->product($transfer);
        $movement = $this->productTransfers->transfer(
            source: $source,
            product: $product,
            amountMinor: (int) $transfer['amount_minor'],
            idempotencyKey: (string) $transfer['idempotency_key'],
            metadata: [
                'wallet_operation' => 'commercial_product_revenue_allocation',
                'transfer_reference' => $transfer['transfer_reference'],
                'recipient_reference' => $transfer['recipient_reference'],
            ],
        );

        return $this->result($movement, 'posted');
    }

    public function reverse(array $transfer): array
    {
        $source = $this->source($transfer);
        $product = $this->product($transfer);
        $movement = $product->transfer(
            $source,
            (int) $transfer['amount_minor'],
            [
                'wallet_operation' => 'commercial_product_revenue_reversal',
                'transfer_reference' => $transfer['reversal_reference'],
                'idempotency_key' => $transfer['reversal_idempotency_key'],
                'amount_minor' => (int) $transfer['amount_minor'],
            ],
        );

        return $this->result($movement, 'reversed');
    }

    /** @param array<string, mixed> $transfer */
    private function source(array $transfer): Wallet
    {
        $reference = trim((string) ($transfer['source_position_reference'] ?? ''));

        if ($reference === '') {
            throw new RuntimeException('Product revenue movement requires a Treasury source position.');
        }

        return $this->positions->resolve($reference);
    }

    /** @param array<string, mixed> $transfer */
    private function product(array $transfer): ProductInterface
    {
        return $this->products->resolve((string) ($transfer['recipient_reference'] ?? ''));
    }

    /** @return array{transfer_uuid:string|null,status:string} */
    private function result(Transfer $movement, string $status): array
    {
        return [
            'transfer_uuid' => $movement->uuid,
            'status' => $status,
        ];
    }
}
