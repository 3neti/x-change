<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

final class CommercialProductRevenueReportResolver extends CommercialOperationsReportResolver
{
    protected function reportName(): string
    {
        return 'products';
    }
}
