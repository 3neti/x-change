<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryInitializationStateData
{
    /**
     * @param  list<string>  $initialized
     * @param  list<string>  $uninitialized
     * @param  list<string>  $incomplete
     */
    public function __construct(
        public array $initialized,
        public array $uninitialized,
        public array $incomplete,
    ) {}
}
