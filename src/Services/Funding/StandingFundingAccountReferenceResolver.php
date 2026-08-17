<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;

final readonly class StandingFundingAccountReferenceResolver
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
    ) {}

    public function resolve(Model $owner, string $provider, string $currency): string
    {
        $positions = TreasuryPosition::query()
            ->whereMorphedTo('principal', $owner)
            ->where('provider', mb_strtolower(trim($provider)))
            ->where('currency', mb_strtoupper(trim($currency)))
            ->where('purpose', TreasuryPositionPurpose::ClientFunds)
            ->where('status', 'active')
            ->get();

        if ($positions->count() > 1) {
            throw new FundingDestinationUnavailable(
                'The Account has more than one compatible Client Funds Position.',
            );
        }

        if ($positions->count() === 1) {
            $reference = $this->walletReference(
                (string) $positions->sole()->internal_ledger_uuid,
            );
            $account = $this->accounts->resolve($reference);

            if (! $this->belongsTo($account, $owner)) {
                throw new FundingDestinationUnavailable(
                    'The Client Funds Position ledger does not belong to the Account holder.',
                );
            }

            return $reference;
        }

        if (method_exists($owner, 'wallets')) {
            $wallet = $owner->wallets()->where('slug', 'platform')->first();
            $uuid = data_get($wallet, 'uuid');

            if (is_string($uuid) && trim($uuid) !== '') {
                return $this->walletReference($uuid);
            }
        }

        throw new FundingDestinationUnavailable(
            'The Account has no stable Client Funds ledger for its Standing Funding Address.',
        );
    }

    private function walletReference(string $uuid): string
    {
        $uuid = trim($uuid);

        if ($uuid === '') {
            throw new FundingDestinationUnavailable(
                'The Client Funds Position has no stable internal ledger reference.',
            );
        }

        return 'wallet:'.$uuid;
    }

    private function belongsTo(object $account, Model $owner): bool
    {
        $holder = data_get($account, 'holder');

        return $holder instanceof Model
            && $holder::class === $owner::class
            && (string) $holder->getKey() === (string) $owner->getKey();
    }
}
