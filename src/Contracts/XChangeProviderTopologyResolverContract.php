<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface XChangeProviderTopologyResolverContract
{
    public function resolve(?string $key = null): XChangeProviderTopologyContract;
}
