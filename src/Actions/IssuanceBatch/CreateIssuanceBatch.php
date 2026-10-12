<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\IssuanceBatchStatus;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\IssuanceBatchItem;

final class CreateIssuanceBatch
{
    /**
     * @param  array<int, array{source_row: int, status: string, amount_minor: int, currency: string, payload: array<string, mixed>}>  $items
     */
    public function handle(
        Model $issuer,
        array $items,
        string $source = 'public.auto-generate.batch',
        ?string $uploaderMobile = null,
    ): IssuanceBatch {
        $validItems = array_values(array_filter($items, static fn (array $item): bool => $item['status'] === 'valid'));
        $fingerprint = hash('sha256', json_encode($validItems, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($issuer, $items, $validItems, $source, $uploaderMobile, $fingerprint): IssuanceBatch {
            $batch = IssuanceBatch::query()->create([
                'source' => $source,
                'status' => IssuanceBatchStatus::Draft,
                'currency' => 'PHP',
                'item_count' => count($items),
                'valid_item_count' => count($validItems),
                'total_minor' => array_sum(array_column($validItems, 'amount_minor')),
                'normalized_fingerprint' => $fingerprint,
                'uploader_mobile_ciphertext' => $uploaderMobile,
                'uploader_mobile_hash' => $uploaderMobile === null ? null : hash('sha256', $uploaderMobile),
                'metadata' => ['issuer_type' => $issuer::class, 'issuer_id' => (string) $issuer->getKey()],
            ]);

            foreach ($items as $ordinal => $item) {
                IssuanceBatchItem::query()->create([
                    'batch_id' => $batch->getKey(),
                    'ordinal' => $ordinal + 1,
                    'source_row' => $item['source_row'],
                    'status' => $item['status'] === 'valid' ? 'pending' : 'invalid',
                    'currency' => $item['currency'],
                    'amount_minor' => $item['amount_minor'],
                    'row_fingerprint' => hash('sha256', json_encode($item, JSON_THROW_ON_ERROR)),
                    'failure_message' => $item['status'] === 'valid' ? null : implode(' ', $item['errors'] ?? []),
                    'payload' => $item['payload'],
                ]);
            }

            return $batch->load('items');
        });
    }
}
