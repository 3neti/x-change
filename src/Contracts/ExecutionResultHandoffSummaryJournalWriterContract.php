<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;

interface ExecutionResultHandoffSummaryJournalWriterContract
{
    public function write(ExecutionResultHandoffSummaryData $summary): ExecutionResultHandoffResultData;
}
