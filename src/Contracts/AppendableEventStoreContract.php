<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface AppendableEventStoreContract
{
    /**
     * @param  array<string,mixed>  $event
     */
    public function append(array $event): void;
}
