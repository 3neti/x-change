<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\IssuanceBatch;

use Illuminate\Http\Request;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\IssuanceBatchItem;

final class IssuanceBatchAccess
{
    public function authorize(Request $request, IssuanceBatch $batch): void
    {
        $token = trim((string) $request->header('X-Issuance-Batch-Token'));

        if ($token !== '' && hash_equals(
            (string) data_get($batch->metadata, 'access_token_hash'),
            hash('sha256', $token),
        )) {
            // Establish the short-lived server session used by subsequent
            // image requests, which cannot carry the custom token header.
            $this->grantSession($request, $batch);

            return;
        }

        $sessionExpiry = $request->session()->get($this->sessionKey($batch));

        abort_unless(
            is_numeric($sessionExpiry) && (int) $sessionExpiry >= now()->timestamp,
            404,
        );
    }

    public function grantSession(Request $request, IssuanceBatch $batch): void
    {
        $request->session()->put($this->sessionKey($batch), now()->addMinutes(30)->timestamp);
    }

    public function authorizeToken(Request $request, IssuanceBatch $batch): void
    {
        $this->authorize($request, $batch);
    }

    public function itemForCode(IssuanceBatch $batch, string $code): IssuanceBatchItem
    {
        return $batch->items()->where('pay_code', $code)->firstOrFail();
    }

    private function sessionKey(IssuanceBatch $batch): string
    {
        return 'x-change.batch-access.'.$batch->reference;
    }
}
