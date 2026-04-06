<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface IssuerResolverContract
{
    /**
     * Resolve issuer from context (e.g., issuer_id).
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(array $context = []): mixed;
}
