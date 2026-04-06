<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface UserResolverContract
{
    /**
     * Resolve the current or relevant user from the given context.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(array $context = []): mixed;
}
