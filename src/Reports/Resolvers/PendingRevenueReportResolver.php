<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\XChange\Services\InstructionRevenueSnapshotService;

class PendingRevenueReportResolver implements ReportResolverInterface
{
    public function __construct(
        protected InstructionRevenueSnapshotService $snapshot,
    ) {}

    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 25,
        int $page = 1,
    ): array {
        $minAmount = isset($filters['min_amount']) ? (float) $filters['min_amount'] : null;
        $typeFilter = isset($filters['type']) ? (string) $filters['type'] : null;

        $result = $this->snapshot->getPendingRevenue($minAmount, 'PHP');

        $rows = collect($result['items'] ?? [])
            ->map(fn (array $row) => [
                'id' => $row['id'] ?? null,
                'index' => $row['index'] ?? null,
                'name' => $row['name'] ?? null,
                'type' => $row['type'] ?? null,
                'balance' => $row['balance'] ?? 0,
                'destination' => data_get($row, 'destination.name', 'Unresolved'),
                'tx_count' => $row['transaction_count'] ?? 0,
            ]);

        if ($typeFilter !== null && $typeFilter !== '') {
            $rows = $rows->where('type', $typeFilter)->values();
        }

        if ($sort !== null && $sort !== '') {
            $rows = strtolower($sortDirection) === 'asc'
                ? $rows->sortBy($sort)->values()
                : $rows->sortByDesc($sort)->values();
        }

        $total = $rows->count();
        $data = $rows->forPage($page, $perPage)->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }
}
