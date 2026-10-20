<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\XChange\Services\Commercial\CommercialOperationsEconomicsReadModel;

abstract class CommercialOperationsReportResolver implements ReportResolverInterface
{
    public function __construct(
        protected CommercialOperationsEconomicsReadModel $readModel,
    ) {}

    abstract protected function reportName(): string;

    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 50,
        int $page = 1,
    ): array {
        return $this->readModel->report($this->reportName(), $filters);
    }
}
