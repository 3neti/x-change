<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Facades\DB;
use LogicException;

final class ProviderEvidenceLock
{
    public function acquire(string $provider, string $transactionId): void
    {
        if (DB::transactionLevel() === 0 || trim($provider) === '' || trim($transactionId) === '') {
            throw new LogicException('Provider evidence must be locked inside a database transaction.');
        }

        $key = hash('sha256', implode("\0", [strtolower(trim($provider)), trim($transactionId)]));
        DB::table('x_change_provider_evidence_locks')->insertOrIgnore(['evidence_key' => $key]);
        DB::table('x_change_provider_evidence_locks')->where('evidence_key', $key)->lockForUpdate()->firstOrFail();
    }
}
