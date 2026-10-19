<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\ProductInterface;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transfer;
use LBHurtado\Wallet\Contracts\ProductWalletTransferContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionWalletResolverContract;
use LBHurtado\XChange\Contracts\CommercialProductResolverContract;
use LBHurtado\XChange\Services\Commercial\BavixCommercialProductRevenueMovement;

function productRevenueTransferPlan(): array
{
    return [
        'source_position_reference' => 'treasury:product',
        'transfers' => [[
            'recipient_reference' => 'product:pay-code',
            'amount_minor' => 800,
            'idempotency_key' => 'transfer-key',
            'transfer_reference' => 'transfer-reference',
            'reversal_reference' => 'reversal-reference',
            'reversal_idempotency_key' => 'reversal-key',
        ]],
    ];
}

it('moves only the planned Product revenue amount and preserves metadata', function (): void {
    $source = Mockery::mock(Wallet::class);
    $product = Mockery::mock(ProductInterface::class);
    $transfer = new Transfer;
    $transfer->setAttribute('uuid', 'transfer-uuid');
    $positions = Mockery::mock(TreasuryPositionWalletResolverContract::class);
    $products = Mockery::mock(CommercialProductResolverContract::class);
    $productTransfers = Mockery::mock(ProductWalletTransferContract::class);
    $positions->shouldReceive('resolve')->once()->with('treasury:product')->andReturn($source);
    $products->shouldReceive('resolve')->once()->with('product:pay-code')->andReturn($product);
    $productTransfers->shouldReceive('transfer')->once()->withArgs(function (...$args) use ($source, $product): bool {
        return $args['source'] === $source
            && $args['product'] === $product
            && $args['amountMinor'] === 800
            && $args['idempotencyKey'] === 'transfer-key';
    })->andReturn($transfer);

    $result = (new BavixCommercialProductRevenueMovement($positions, $products, $productTransfers))
        ->post(productRevenueTransferPlan()['transfers'][0] + ['source_position_reference' => 'treasury:product']);

    expect($result)->toMatchArray(['transfer_uuid' => 'transfer-uuid', 'status' => 'posted']);
});

it('uses a separate deterministic reversal reference', function (): void {
    $source = Mockery::mock(Wallet::class);
    $product = Mockery::mock(ProductInterface::class);
    $transfer = new Transfer;
    $transfer->setAttribute('uuid', 'reversal-uuid');
    $positions = Mockery::mock(TreasuryPositionWalletResolverContract::class);
    $products = Mockery::mock(CommercialProductResolverContract::class);
    $productTransfers = Mockery::mock(ProductWalletTransferContract::class);
    $positions->shouldReceive('resolve')->once()->andReturn($source);
    $products->shouldReceive('resolve')->once()->andReturn($product);
    $product->shouldReceive('transfer')->once()->with($source, 800, Mockery::on(
        static fn (array $meta): bool => $meta['idempotency_key'] === 'reversal-key'
            && $meta['wallet_operation'] === 'commercial_product_revenue_reversal',
    ))->andReturn($transfer);

    $result = (new BavixCommercialProductRevenueMovement($positions, $products, $productTransfers))
        ->reverse(productRevenueTransferPlan()['transfers'][0] + ['source_position_reference' => 'treasury:product']);

    expect($result)->toMatchArray(['transfer_uuid' => 'reversal-uuid', 'status' => 'reversed']);
});
