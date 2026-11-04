<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LogicException;

final class LockIssuanceFundingAttribution
{
    public function handle(string $provider, string $currency): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Funding attribution must be locked inside a database transaction.');
        }

        $key = hash('sha256', implode('|', [$provider, $currency]));
        DB::table('x_change_issuance_funding_attribution_locks')->insertOrIgnore(['scope_key' => $key]);
        DB::table('x_change_issuance_funding_attribution_locks')->where('scope_key', $key)->lockForUpdate()->first();
    }
}
