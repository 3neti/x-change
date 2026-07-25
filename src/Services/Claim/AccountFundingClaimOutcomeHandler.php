<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\TransferPayCodeIntoAccount;
use LBHurtado\XChange\Contracts\VoucherClaimOutcomeHandlerContract;
use LBHurtado\XChange\Exceptions\VoucherClaimOutcomeConflict;

final readonly class AccountFundingClaimOutcomeHandler implements VoucherClaimOutcomeHandlerContract
{
    public function __construct(
        private TransferPayCodeIntoAccount $transfer,
    ) {}

    public function key(): string
    {
        return 'account_funding';
    }

    public function execute(
        Voucher $voucher,
        array $payload,
        ?Authenticatable $claimant = null,
    ): mixed {
        if (! $claimant instanceof Model) {
            throw new VoucherClaimOutcomeConflict(
                'Account Funding requires an authenticated Account owner.',
            );
        }

        return $this->transfer->handle($voucher, $claimant);
    }
}
