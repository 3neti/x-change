<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

use Illuminate\Support\Carbon;
use LBHurtado\Instruction\Models\InstructionItem;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\XChange\Models\RevenueCollection;

class RevenueByInstructionReportResolver implements ReportResolverInterface
{
    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 10,
        int $page = 1
    ): array {
        $instructionItemModel = config('x-change.revenue.instruction_item_model', InstructionItem::class);
        $instructionItemTable = (new $instructionItemModel())->getTable();
        $timezone = config('app.timezone', 'Asia/Manila');

        $query = RevenueCollection::query()
            ->join($instructionItemTable, "{$instructionItemTable}.id", '=', 'revenue_collections.instruction_item_id')
            ->select([
                "{$instructionItemTable}.index",
                "{$instructionItemTable}.name",
                "{$instructionItemTable}.type",
            ])
            ->selectRaw('COUNT(revenue_collections.id) as collections_count')
            ->selectRaw('SUM(revenue_collections.amount) as total_amount_minor')
            ->selectRaw('MAX(revenue_collections.created_at) as last_collected_at')
            ->groupBy(
                "{$instructionItemTable}.index",
                "{$instructionItemTable}.name",
                "{$instructionItemTable}.type",
            );

        if (($type = data_get($filters, 'type')) !== null && $type !== '') {
            $query->where("{$instructionItemTable}.type", (string) $type);
        }

        if (($min = data_get($filters, 'min_amount')) !== null && $min !== '') {
            $query->havingRaw('SUM(revenue_collections.amount) >= ?', [(int) round(((float) $min) * 100)]);
        }

        $sortable = [
            'index' => 'index',
            'name' => 'name',
            'type' => 'type',
            'collections_count' => 'collections_count',
            'total_amount' => 'total_amount_minor',
            'last_collected_at' => 'last_collected_at',
        ];

        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        if ($sort !== null && isset($sortable[$sort])) {
            $query->orderBy($sortable[$sort], $direction);
        } else {
            $query->orderBy('total_amount_minor', 'desc');
        }

        $rows = $query->get();
        $total = $rows->count();

        $data = $rows
            ->forPage($page, $perPage)
            ->map(function (object $row) use ($timezone): array {
                return [
                    'index' => $row->index,
                    'name' => $row->name,
                    'type' => $row->type,
                    'collections_count' => (int) $row->collections_count,
                    'total_amount' => ((int) $row->total_amount_minor) / 100,
                    'last_collected_at' => $row->last_collected_at
                        ? Carbon::parse($row->last_collected_at)
                            ->setTimezone($timezone)
                            ->toIso8601String()
                        : null,
                ];
            })
            ->values()
            ->all();

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
