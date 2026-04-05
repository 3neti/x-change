<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface TerminologyServiceContract
{
    public function term(string $key, ?string $default = null): string;

    /**
     * @param  array<string, string|int|float|bool|null>  $replace
     */
    public function message(string $key, array $replace = [], ?string $default = null): string;
}
