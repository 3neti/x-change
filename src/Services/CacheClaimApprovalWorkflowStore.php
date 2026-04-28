<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;

class CacheClaimApprovalWorkflowStore implements ClaimApprovalWorkflowStoreContract
{
    public function __construct(
        protected CacheRepository $cache,
    ) {}

    public function get(Voucher $voucher): ?array
    {
        $value = $this->cache->get($this->key($voucher));

        return is_array($value) ? $value : null;
    }

    public function put(Voucher $voucher, array $workflow): void
    {
        $this->cache->put(
            $this->key($voucher),
            $workflow,
            now()->addMinutes((int) config('x-change.claim_approval.ttl_minutes', 15)),
        );
    }

    public function forget(Voucher $voucher): void
    {
        $this->cache->forget($this->key($voucher));
    }

    protected function key(Voucher $voucher): string
    {
        return 'x-change:claim-approval:'.$voucher->code;
    }
}
