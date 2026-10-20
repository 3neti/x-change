<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CommercialOperationsEconomicsReadModel
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(string $report, array $filters = []): array
    {
        return match ($report) {
            'summary' => $this->summary($filters),
            'waterfall' => $this->waterfall($filters),
            'products' => $this->products($filters),
            'positions' => $this->positions($filters),
            'movements' => $this->movements($filters),
            'exceptions' => $this->exceptions($filters),
            default => throw new \InvalidArgumentException("Unknown operations economics report [{$report}]."),
        };
    }

    /** @param  array<string, mixed>  $filters */
    public function summary(array $filters): array
    {
        [$start, $end] = $this->period($filters);
        $checkouts = DB::table('x_change_checkouts')->whereBetween('created_at', [$start, $end]);
        $orders = DB::table('x_change_pay_code_issuance_funding_orders')->whereBetween('created_at', [$start, $end]);
        $sales = DB::table('x_change_commercial_sales')->whereBetween('accepted_at', [$start, $end]);
        $claims = DB::table('voucher_claims')->whereBetween('created_at', [$start, $end]);

        $issued = (clone $orders)->whereNotNull('voucher_id')->count();
        $principal = (int) (clone $orders)->whereNotNull('voucher_id')->sum('on_demand_amount_minor');
        $salesAmount = (int) (clone $sales)->sum('total_price_minor');

        return [
            'data' => [[
                'checkouts' => (int) $checkouts->count(),
                'payments_received' => (int) (clone $orders)->whereNotNull('funded_at')->count(),
                'orders_issued' => $issued,
                'claims' => (int) $claims->count(),
                'principal_minor' => $principal,
                'commercial_sales_minor' => $salesAmount,
                'provider_cost_minor' => (int) DB::table('x_change_commercial_provider_cost_settlements')->whereBetween('created_at', [$start, $end])->sum('observed_amount_minor'),
                'product_revenue_minor' => (int) DB::table('x_change_commercial_allocations')->whereBetween('created_at', [$start, $end])->where('category', 'product_revenue')->sum('amount_minor'),
                'reversed_minor' => $this->reversedAmount($start, $end),
                'exceptions' => (int) $this->exceptionQuery($start, $end)->count(),
                'state' => 'read_only',
            ]],
            'meta' => $this->meta($filters, $start, $end),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function waterfall(array $filters): array
    {
        [$start, $end] = $this->period($filters);
        $rows = DB::table('x_change_commercial_allocations')
            ->whereBetween('created_at', [$start, $end])
            ->select('category', 'destination_position_reference', 'status')
            ->selectRaw('COUNT(*) as line_count, SUM(amount_minor) as amount_minor')
            ->groupBy('category', 'destination_position_reference', 'status')
            ->orderByDesc('amount_minor')
            ->get()
            ->map(fn (object $row): array => [
                'category' => $row->category,
                'destination' => $row->destination_position_reference,
                'status' => $row->status,
                'line_count' => (int) $row->line_count,
                'amount_minor' => (int) $row->amount_minor,
            ])->all();

        return ['data' => $rows, 'meta' => $this->meta($filters, $start, $end, count($rows))];
    }

    /** @param  array<string, mixed>  $filters */
    public function products(array $filters): array
    {
        [$start, $end] = $this->period($filters);
        $rows = DB::table('x_change_commercial_allocations')
            ->whereBetween('created_at', [$start, $end])
            ->where('category', 'product_revenue')
            ->select('recipient_reference', 'category', 'status')
            ->selectRaw('COUNT(*) as line_count, SUM(amount_minor) as amount_minor')
            ->groupBy('recipient_reference', 'category', 'status')
            ->orderByDesc('amount_minor')
            ->get()
            ->map(fn (object $row): array => [
                'product' => $row->recipient_reference,
                'category' => $row->category,
                'status' => $row->status,
                'line_count' => (int) $row->line_count,
                'amount_minor' => (int) $row->amount_minor,
            ])->all();

        return ['data' => $rows, 'meta' => $this->meta($filters, $start, $end, count($rows))];
    }

    /** @param  array<string, mixed>  $filters */
    public function positions(array $filters): array
    {
        [$start, $end] = $this->period($filters);
        $rows = DB::table('x_change_commercial_allocations')
            ->whereBetween('created_at', [$start, $end])
            ->select('destination_position_reference', 'currency', 'status')
            ->selectRaw('COUNT(*) as movement_count, SUM(amount_minor) as amount_minor')
            ->groupBy('destination_position_reference', 'currency', 'status')
            ->orderByDesc('amount_minor')
            ->get()
            ->map(fn (object $row): array => [
                'position' => $row->destination_position_reference,
                'currency' => $row->currency,
                'status' => $row->status,
                'movement_count' => (int) $row->movement_count,
                'amount_minor' => (int) $row->amount_minor,
            ])->all();

        return ['data' => $rows, 'meta' => $this->meta($filters, $start, $end, count($rows))];
    }

    /** @param  array<string, mixed>  $filters */
    public function movements(array $filters): array
    {
        [$start, $end] = $this->period($filters);
        $sales = DB::table('x_change_commercial_sales')->whereBetween('accepted_at', [$start, $end])->get();
        $costs = DB::table('x_change_commercial_provider_cost_settlements')->whereBetween('observed_at', [$start, $end])->get();
        $rows = $sales->map(fn (object $row): array => [
            'reference' => $row->reference,
            'type' => 'commercial_sale',
            'status' => $row->status,
            'amount_minor' => (int) $row->total_price_minor,
            'currency' => $row->currency,
            'occurred_at' => $row->accepted_at,
        ])->merge($costs->map(fn (object $row): array => [
            'reference' => $row->evidence_reference,
            'type' => 'provider_cost',
            'status' => $row->status,
            'amount_minor' => (int) $row->observed_amount_minor,
            'currency' => $row->currency,
            'occurred_at' => $row->observed_at,
        ]))->sortByDesc('occurred_at')->values()->all();

        return ['data' => $rows, 'meta' => $this->meta($filters, $start, $end, count($rows))];
    }

    /** @param  array<string, mixed>  $filters */
    public function exceptions(array $filters): array
    {
        [$start, $end] = $this->period($filters);
        $rows = $this->exceptionQuery($start, $end)->orderByDesc('created_at')->get()->map(fn (object $row): array => [
            'reference' => $row->id,
            'type' => 'provider_cost_variance',
            'status' => $row->status,
            'variance_minor' => (int) $row->variance_amount_minor,
            'currency' => $row->currency,
            'occurred_at' => $row->created_at,
        ])->all();

        return ['data' => $rows, 'meta' => $this->meta($filters, $start, $end, count($rows))];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(array $filters): array
    {
        $timezone = config('app.timezone', 'Asia/Manila');
        $end = CarbonImmutable::now($timezone)->endOfDay()->utc();
        $period = (string) ($filters['period'] ?? 'today');
        $start = match ($period) {
            'seven_days' => $end->subDays(6)->startOfDay(),
            'thirty_days' => $end->subDays(29)->startOfDay(),
            'custom' => $this->parseDate($filters['from'] ?? null, $timezone)?->startOfDay()->utc() ?? $end->startOfDay(),
            default => $end->startOfDay(),
        };

        if ($period === 'custom' && ($customEnd = $this->parseDate($filters['to'] ?? null, $timezone)) !== null) {
            $end = $customEnd->endOfDay()->utc();
        }

        return [$start, $end];
    }

    private function reversedAmount(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (! Schema::hasTable('x_change_commercial_product_revenue_reversals')) {
            return 0;
        }

        return (int) DB::table('x_change_commercial_product_revenue_reversals')
            ->whereBetween('reversed_at', [$start, $end])
            ->sum('amount_minor');
    }

    private function parseDate(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function exceptionQuery(CarbonImmutable $start, CarbonImmutable $end): \Illuminate\Database\Query\Builder
    {
        return DB::table('x_change_commercial_provider_cost_settlements')
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($query): void {
                $query->where('variance_amount_minor', '!=', 0)->orWhereNotIn('status', ['settled', 'recognized']);
            });
    }

    /** @return array<string, mixed> */
    private function meta(array $filters, CarbonImmutable $start, CarbonImmutable $end, ?int $total = null): array
    {
        return [
            'filters' => $filters,
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'currency' => $filters['currency'] ?? 'PHP',
            'state' => 'read_only',
            'total' => $total ?? 1,
            'page' => 1,
            'per_page' => $total ?? 1,
            'last_page' => 1,
        ];
    }
}
