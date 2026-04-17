<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Reports\Resolvers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\XChange\Models\RevenueCollection;

class RevenueCollectionsReportResolver implements ReportResolverInterface
{
    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 10,
        int $page = 1
    ): array {
        $query = RevenueCollection::query()
            ->with(['instructionItem', 'destination']);

        if ($index = Arr::get($filters, 'index')) {
            $query->whereHas('instructionItem', function (Builder $q) use ($index) {
                $q->where('index', $index);
            });
        }

        if (($min = Arr::get($filters, 'min_amount')) !== null && $min !== '') {
            $query->where('amount', '>=', (int) round(((float) $min) * 100));
        }

        $sortable = [
            'collected_at' => 'created_at',
            'amount' => 'amount',
        ];

        if ($sort !== null && isset($sortable[$sort])) {
            $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortable[$sort], $direction);
        } else {
            $query->latest('created_at');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(function (RevenueCollection $row): array {
                return [
                    'id' => $row->id,
                    'index' => $row->instructionItem?->index,
                    'name' => $row->instructionItem?->name,
                    'amount' => ((int) $row->amount) / 100,
                    'destination' => $row->destination_name ?? $this->destinationName($row),
                    'collected_at' => $row->created_at?->toIso8601String(),
                    'transfer_uuid' => $row->transfer_uuid,
                ];
            })
            ->values()
            ->all();

        return [
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    protected function destinationName(RevenueCollection $row): string
    {
        $destination = $row->destination;

        if (! $destination) {
            return 'Unknown';
        }

        return match (true) {
            isset($destination->name) && filled($destination->name) => (string) $destination->name,
            isset($destination->email) && filled($destination->email) => (string) $destination->email,
            method_exists($destination, 'getName') => (string) $destination->getName(),
            default => class_basename($destination).' #'.$destination->getKey(),
        };
    }
}
