<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Models\ProviderAccountLink;

interface ProviderAccountLinkRepositoryContract
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function storeFromProvisioningResult(mixed $owner, array $result): ProviderAccountLink;

    public function findReadyForOwner(mixed $owner, string $provider, ?string $mode = null): ?ProviderAccountLink;

    public function findLatestForOwner(mixed $owner, string $provider, ?string $mode = null): ?ProviderAccountLink;
}
