<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface UserLifecycleServiceContract
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|object
     */
    public function create(array $payload): mixed;

    /**
     * @return array<string,mixed>|object|null
     */
    public function show(string $user): mixed;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|object
     */
    public function submitKyc(string $user, array $payload): mixed;

    /**
     * @return array<string,mixed>|object|null
     */
    public function showKyc(string $user): mixed;
}
