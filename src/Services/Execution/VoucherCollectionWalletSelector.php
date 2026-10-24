<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class VoucherCollectionWalletSelector
{
    public function __construct(
        private VoucherValueFlowPolicy $flows,
        private ValueFlowAddressResolver $addresses,
        private VoucherCollectionWalletResolverContract $defaults,
        private WalletAccessContract $wallets,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    public function resolve(Voucher $voucher, string $provider, string $currency): Wallet
    {
        $destination = $this->flows->destination($voucher);

        if ($destination === null) {
            return $this->defaults->resolve($voucher);
        }

        $connections = collect($this->connections->active())
            ->filter(static fn ($connection): bool => $connection->provider === strtolower($provider)
                && $connection->currency === strtoupper($currency))
            ->values();

        if ($connections->count() !== 1) {
            throw new RuntimeException('Value flow collection requires exactly one active Treasury connection.');
        }

        $position = $this->addresses->clientFunds(
            $destination,
            $connections->sole()->reference,
            strtoupper($currency),
        );
        $principal = TreasuryPosition::query()
            ->where('position_reference', $position->positionReference)
            ->sole()
            ->principal;
        $wallet = $principal instanceof Model
            ? $this->wallets->resolveForUser($principal)
            : null;

        if (! $wallet instanceof Wallet) {
            throw new RuntimeException('The value flow collection destination has no Account wallet.');
        }

        return $wallet;
    }
}
