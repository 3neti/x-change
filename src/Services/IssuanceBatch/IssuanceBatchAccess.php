<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\IssuanceBatch;

use Illuminate\Http\Request;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\IssuanceBatchItem;

final class IssuanceBatchAccess
{
    public function authorizeToken(Request $request, IssuanceBatch $batch): void
    {
        $token = trim((string) $request->header('X-Issuance-Batch-Token'));

        abort_unless(
            $token !== ''
                && hash_equals(
                    (string) data_get($batch->metadata, 'access_token_hash'),
                    hash('sha256', $token),
                ),
            404,
        );
    }

    public function itemForCode(IssuanceBatch $batch, string $code): IssuanceBatchItem
    {
        return $batch->items()->where('pay_code', $code)->firstOrFail();
    }
}
