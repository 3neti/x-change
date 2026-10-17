<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\VoucherInstruction\Commands;

use Closure;

final class TipCommand
{
    /**
     * @param  Closure(int, int): int|null  $amountPicker
     */
    public function __construct(
        private readonly int $defaultMinimum = 50,
        private readonly int $defaultMaximum = 100,
        private readonly ?Closure $amountPicker = null,
    ) {}

    /**
     * @return array{amount: float, minimum: int, maximum: int}|null
     */
    public function interpret(string $prompt): ?array
    {
        if (preg_match('/^tip(?:\s+([0-9]+)(?:\s*[-–]\s*([0-9]+))?)?\s*$/i', trim($prompt), $matches) !== 1) {
            return null;
        }

        $minimum = isset($matches[1]) && $matches[1] !== ''
            ? (int) $matches[1]
            : $this->defaultMinimum;
        $maximum = isset($matches[2]) && $matches[2] !== ''
            ? (int) $matches[2]
            : (isset($matches[1]) && $matches[1] !== '' ? $minimum : $this->defaultMaximum);

        if ($minimum < 1 || $maximum < $minimum) {
            return null;
        }

        $amount = $this->amountPicker?->__invoke($minimum, $maximum)
            ?? random_int($minimum, $maximum);

        return [
            'amount' => (float) $amount,
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }
}
