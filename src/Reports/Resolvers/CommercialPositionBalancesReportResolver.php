<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

final class CommercialPositionBalancesReportResolver extends CommercialOperationsReportResolver
{
    protected function reportName(): string
    {
        return 'positions';
    }
}
