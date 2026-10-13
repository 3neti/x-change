<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use LBHurtado\XChange\Actions\IssuanceBatch\CreateIssuanceBatch;
use LBHurtado\XChange\Actions\IssuanceBatch\PrepareIssuanceBatchFunding;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchImportNormalizer;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchTabularReader;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicIssuanceBatchController extends Controller
{
    public function preview(Request $request, IssuanceBatchTabularReader $reader, IssuanceBatchImportNormalizer $normalizer): JsonResponse
    {
        $this->decodeJsonInput($request);
        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:'.(int) ceil(config('x-change.issuance_batches.maximum_upload_bytes', 10_485_760) / 1024)],
            'headers' => ['nullable', 'array'],
            'rows' => ['nullable', 'array'],
            'mapping' => ['nullable', 'array'],
        ]);
        $table = $this->readInput($request, $reader, $validated['headers'] ?? null, $validated['rows'] ?? null);
        $mapping = $this->mapping($validated['mapping'] ?? null, $table['headers'], $normalizer);
        $normalized = $normalizer->normalize($table['rows'], $mapping);

        return response()->json([
            'sheet' => $table['sheet'],
            'headers' => $table['headers'],
            'items' => $normalized['items'],
            'total_minor' => $normalized['total_minor'],
            'errors' => $normalized['errors'],
            'mapping' => $mapping,
            'can_create' => $normalized['errors'] === [] && $normalized['items'] !== [],
        ]);
    }

    public function store(Request $request, ConfiguredCommercialPrincipalResolver $resolver, IssuanceBatchTabularReader $reader, IssuanceBatchImportNormalizer $normalizer, CreateIssuanceBatch $create): JsonResponse
    {
        $this->decodeJsonInput($request);
        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:'.(int) ceil(config('x-change.issuance_batches.maximum_upload_bytes', 10_485_760) / 1024)],
            'headers' => ['nullable', 'array'],
            'rows' => ['nullable', 'array'],
            'mapping' => ['nullable', 'array'],
            'mobile' => ['required', 'string', 'max:32'],
        ]);
        $table = $this->readInput($request, $reader, $validated['headers'] ?? null, $validated['rows'] ?? null);
        $mapping = $this->mapping($validated['mapping'] ?? null, $table['headers'], $normalizer);
        $normalized = $normalizer->normalize($table['rows'], $mapping);
        abort_if($normalized['errors'] !== [] || $normalized['items'] === [], 422, implode(' ', $normalized['errors'] ?: ['No valid rows were found.']));
        $token = Str::random(64);
        $batch = $create->handle($resolver->resolve(), $normalized['items'], uploaderMobile: $validated['mobile']);
        $batch->forceFill(['metadata' => [...(array) $batch->metadata, 'access_token_hash' => hash('sha256', $token)]])->save();

        return response()->json(['reference' => $batch->reference, 'access_token' => $token, 'item_count' => $batch->valid_item_count, 'total_minor' => $batch->total_minor], 201);
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<string, string>>, sheet: ?string} */
    private function readInput(Request $request, IssuanceBatchTabularReader $reader, ?array $headers, ?array $rows): array
    {
        if ($request->hasFile('file')) {
            return $reader->read($request->file('file'));
        }

        abort_unless(is_array($rows) && $rows !== [], 422, 'Upload a file or paste recipient rows.');
        $headers = array_values(array_filter(array_map(static fn (mixed $header): string => trim((string) $header), $headers ?? []), static fn (string $header): bool => $header !== ''));
        if ($headers === []) {
            $headers = ['name', 'mobile', 'email', 'amount'];
        }
        abort_if(count($headers) > (int) config('x-change.issuance_batches.maximum_columns', 30), 422, 'The worksheet contains too many columns.');
        abort_if(count($rows) > (int) config('x-change.issuance_batches.maximum_rows', 10000), 422, 'The worksheet contains too many rows.');

        return [
            'headers' => $headers,
            'rows' => array_map(function (mixed $row) use ($headers): array {
                abort_unless(is_array($row), 422, 'Each pasted row must be an array.');
                $values = array_values($row);

                return array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), array_slice(array_pad($values, count($headers), ''), 0, count($headers)))) ?: [];
            }, $rows),
            'sheet' => null,
        ];
    }

    /** @param array<string, string>|null $mapping @param array<int, string> $headers */
    private function mapping(?array $mapping, array $headers, IssuanceBatchImportNormalizer $normalizer): array
    {
        $mapping ??= $normalizer->detectMapping($headers);

        return array_filter($mapping, static fn (mixed $header, mixed $canonical): bool => is_string($canonical) && is_string($header) && in_array($header, $headers, true));
    }

    private function decodeJsonInput(Request $request): void
    {
        foreach (['headers', 'rows', 'mapping'] as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $request->merge([$key => $decoded]);
                }
            }
        }
    }

    public function show(Request $request, IssuanceBatch $batch, IssuanceBatchAccess $batches): JsonResponse
    {
        $batches->authorize($request, $batch);

        $batch->load('items');

        return response()->json([
            'reference' => $batch->reference,
            'status' => $batch->status,
            'item_count' => $batch->item_count,
            'valid_item_count' => $batch->valid_item_count,
            'total_minor' => $batch->total_minor,
            'expires_at' => $batch->expires_at?->toIso8601String(),
            'funded_at' => $batch->funded_at?->toIso8601String(),
            'issued_at' => $batch->issued_at?->toIso8601String(),
            'items' => $batch->items->map(fn ($item): array => [
                'ordinal' => $item->ordinal,
                'source_row' => $item->source_row,
                'name' => data_get($item->payload, 'name'),
                'mobile' => data_get($item->payload, 'mobile'),
                'email' => data_get($item->payload, 'email'),
                'amount_minor' => $item->amount_minor,
                'pay_code' => $item->pay_code,
                'claim_url' => $item->claim_url,
                'status' => $item->status,
                'issued_at' => $item->issued_at?->toIso8601String(),
                'failure_message' => $item->failure_message,
            ])->values()->all(),
        ]);
    }

    public function fund(Request $request, IssuanceBatch $batch, ConfiguredCommercialPrincipalResolver $resolver, PrepareIssuanceBatchFunding $funding, CheckoutLifecycle $checkouts, PublicIssuanceOrderAccess $orders, OnDemandIssuanceFundingOrderPresenter $presenter, IssuanceBatchAccess $batches): JsonResponse
    {
        $batches->authorize($request, $batch);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191']]);
        $order = $funding->handle($resolver->resolve(), $batch, $validated['idempotency_key']);
        $checkouts->place($order);
        $token = $orders->bind($order, $request);

        return response()->json([
            'batch_reference' => $batch->reference,
            'funding_order_reference' => $order->reference,
            'expected_payment_minor' => $order->expected_payment_minor,
            'funding_order' => $presenter->present($order, $token, true),
        ], 202);
    }

    public function download(Request $request, IssuanceBatch $batch, IssuanceBatchAccess $batches): StreamedResponse
    {
        $batches->authorize($request, $batch);

        return response()->streamDownload(function () use ($batch): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Row', 'Name', 'Mobile', 'Email', 'Amount', 'Pay Code', 'Status', 'Claim URL']);
            foreach ($batch->items as $item) {
                fputcsv($handle, [$item->source_row, data_get($item->payload, 'name'), data_get($item->payload, 'mobile'), data_get($item->payload, 'email'), number_format($item->amount_minor / 100, 2, '.', ''), $item->pay_code, $item->status, $item->claim_url]);
            }
            fclose($handle);
        }, $batch->reference.'.csv', ['Content-Type' => 'text/csv']);
    }
}
