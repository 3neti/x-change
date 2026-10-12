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
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchImportNormalizer;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchTabularReader;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicIssuanceBatchController extends Controller
{
    public function preview(Request $request, IssuanceBatchTabularReader $reader, IssuanceBatchImportNormalizer $normalizer): JsonResponse
    {
        $file = $request->file('file');
        abort_unless($file !== null, 422, 'A CSV, XLS, or XLSX file is required.');
        $request->validate(['file' => ['file', 'max:'.(int) ceil(config('x-change.issuance_batches.maximum_upload_bytes', 10_485_760) / 1024)]]);
        $table = $reader->read($file);
        $normalized = $normalizer->normalize($table['rows'], $normalizer->detectMapping($table['headers']));

        return response()->json([
            'sheet' => $table['sheet'],
            'headers' => $table['headers'],
            'items' => $normalized['items'],
            'total_minor' => $normalized['total_minor'],
            'errors' => $normalized['errors'],
            'can_create' => $normalized['errors'] === [] && $normalized['items'] !== [],
        ]);
    }

    public function store(Request $request, ConfiguredCommercialPrincipalResolver $resolver, IssuanceBatchTabularReader $reader, IssuanceBatchImportNormalizer $normalizer, CreateIssuanceBatch $create): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.(int) ceil(config('x-change.issuance_batches.maximum_upload_bytes', 10_485_760) / 1024)],
            'mobile' => ['required', 'string', 'max:32'],
        ]);
        $table = $reader->read($validated['file']);
        $normalized = $normalizer->normalize($table['rows'], $normalizer->detectMapping($table['headers']));
        abort_if($normalized['errors'] !== [] || $normalized['items'] === [], 422, implode(' ', $normalized['errors'] ?: ['No valid rows were found.']));
        $token = Str::random(64);
        $batch = $create->handle($resolver->resolve(), $normalized['items'], uploaderMobile: $validated['mobile']);
        $batch->forceFill(['metadata' => [...(array) $batch->metadata, 'access_token_hash' => hash('sha256', $token)]])->save();

        return response()->json(['reference' => $batch->reference, 'access_token' => $token, 'item_count' => $batch->valid_item_count, 'total_minor' => $batch->total_minor], 201);
    }

    public function show(Request $request, IssuanceBatch $batch): JsonResponse
    {
        $this->authorizeToken($request, $batch);

        return response()->json($batch->load('items'));
    }

    public function fund(Request $request, IssuanceBatch $batch, ConfiguredCommercialPrincipalResolver $resolver, PrepareIssuanceBatchFunding $funding, PublicIssuanceOrderAccess $orders, OnDemandIssuanceFundingOrderPresenter $presenter): JsonResponse
    {
        $this->authorizeToken($request, $batch);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:191']]);
        $order = $funding->handle($resolver->resolve(), $batch, $validated['idempotency_key']);
        $token = $orders->bind($order, $request);

        return response()->json([
            'batch_reference' => $batch->reference,
            'funding_order_reference' => $order->reference,
            'expected_payment_minor' => $order->expected_payment_minor,
            'funding_order' => $presenter->present($order, $token, true),
        ], 202);
    }

    public function download(Request $request, IssuanceBatch $batch): StreamedResponse
    {
        $this->authorizeToken($request, $batch);

        return response()->streamDownload(function () use ($batch): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Row', 'Name', 'Mobile', 'Email', 'Amount', 'Pay Code', 'Status']);
            foreach ($batch->items as $item) {
                fputcsv($handle, [$item->source_row, data_get($item->payload, 'name'), data_get($item->payload, 'mobile'), data_get($item->payload, 'email'), number_format($item->amount_minor / 100, 2, '.', ''), $item->pay_code, $item->status]);
            }
            fclose($handle);
        }, $batch->reference.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeToken(Request $request, IssuanceBatch $batch): void
    {
        $token = trim((string) $request->header('X-Issuance-Batch-Token'));
        abort_unless($token !== '' && hash_equals((string) data_get($batch->metadata, 'access_token_hash'), hash('sha256', $token)), 404);
    }
}
