<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;

final readonly class DefaultClaimShareCardUrlResolver implements ClaimShareCardUrlResolverContract
{
    public function __construct(
        private RiderStampArtifactStoreContract $artifacts,
    ) {}

    public function resolve(Voucher $voucher): string
    {
        $artifact = $this->artifacts->descriptor($voucher);

        if (
            $artifact !== null
            && $this->artifacts->read($voucher) !== null
            && Route::has('x-change.claim.share-card.artifact')
        ) {
            return route('x-change.claim.share-card.artifact', [
                'code' => $voucher->code,
                'sha256' => $artifact->sha256,
            ]);
        }

        return route('x-change.claim.share-card', ['code' => $voucher->code]);
    }
}
