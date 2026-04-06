<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface IssuerOnboardingContract
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function onboard(array $input): mixed;
}
