<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Configuration\InstructionCapabilityReadinessData;

interface InstructionCapabilityContributor
{
    /**
     * @return iterable<InstructionCapabilityReadinessData>
     */
    public function instructionCapabilities(): iterable;
}
