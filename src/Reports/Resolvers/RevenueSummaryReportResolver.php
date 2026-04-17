<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

use Carbon\CarbonImmutable;
use LBHurtado\Instruction\Models\InstructionItem;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\XChange\Models\RevenueCollection;

class RevenueSummaryReportResolver implements ReportResolverInterface
{
    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 10,
        int $page = 1
    ): array {
        $timezone = config('app.timezone', 'Asia/Manila');

        $now = CarbonImmutable::now($timezone);
        $startOfTodayUtc = $now->startOfDay()->utc();
        $startOfWeekUtc = $now->startOfWeek()->utc();

        $baseQuery = RevenueCollection::query();

        $collectedTodayMinor = (clone $baseQuery)
            ->where('created_at', '>=', $startOfTodayUtc)
            ->sum('amount');

        $collectedThisWeekMinor = (clone $baseQuery)
            ->where('created_at', '>=', $startOfWeekUtc)
            ->sum('amount');

        $collectedLifetimeMinor = (clone $baseQuery)->sum('amount');

        $collectionsToday = (clone $baseQuery)
            ->where('created_at', '>=', $startOfTodayUtc)
            ->count();

        $collectionsThisWeek = (clone $baseQuery)
            ->where('created_at', '>=', $startOfWeekUtc)
            ->count();

        $collectionsLifetime = (clone $baseQuery)->count();

        $lastCollection = RevenueCollection::query()
            ->latest('created_at')
            ->first();

        $instructionItemModel = config('x-change.revenue.instruction_item_model', InstructionItem::class);
        $instructionItemTable = (new $instructionItemModel)->getTable();

        $topInstruction = RevenueCollection::query()
            ->join($instructionItemTable, "{$instructionItemTable}.id", '=', 'revenue_collections.instruction_item_id')
            ->select([
                "{$instructionItemTable}.index",
                "{$instructionItemTable}.name",
            ])
            ->selectRaw('SUM(revenue_collections.amount) as total_amount')
            ->groupBy("{$instructionItemTable}.index", "{$instructionItemTable}.name")
            ->orderByRaw('SUM(revenue_collections.amount) DESC')
            ->first();

        $row = [
            'collected_today' => $collectedTodayMinor / 100,
            'collected_this_week' => $collectedThisWeekMinor / 100,
            'collected_lifetime' => $collectedLifetimeMinor / 100,
            'collections_today' => $collectionsToday,
            'collections_this_week' => $collectionsThisWeek,
            'collections_lifetime' => $collectionsLifetime,
            'last_collected_at' => $lastCollection?->created_at?->setTimezone($timezone)->toIso8601String(),
            'top_instruction_index' => data_get($topInstruction, 'index'),
            'top_instruction_name' => data_get($topInstruction, 'name'),
            'top_instruction_amount' => ((int) data_get($topInstruction, 'total_amount', 0)) / 100,
        ];

        return [
            'data' => [$row],
            'meta' => [
                'total' => 1,
                'page' => $page,
                'per_page' => 1,
                'last_page' => 1,
            ],
        ];
    }
}
