<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\XChange\Contracts\VoucherCollectionPostingContract;
use RuntimeException;

final class VoucherCollectionPostingRegistry
{
    /**
     * @var array<string, VoucherCollectionPostingContract>
     */
    private array $postings = [];

    /**
     * @param  iterable<VoucherCollectionPostingContract>  $postings
     */
    public function __construct(iterable $postings)
    {
        foreach ($postings as $posting) {
            $this->postings[$posting->driver()] = $posting;
        }
    }

    public function resolve(string $driver): VoucherCollectionPostingContract
    {
        return $this->postings[$driver]
            ?? throw new RuntimeException(
                "Voucher collection posting driver [{$driver}] is not registered.",
            );
    }
}
