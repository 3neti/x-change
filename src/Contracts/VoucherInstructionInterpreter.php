<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface VoucherInstructionInterpreter
{
    /**
     * @return array<string, mixed>
     */
    public function interpret(string $prompt): array;
}
