<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\IssuanceBatch\CreateIssuanceBatch;
use LBHurtado\XChange\Actions\IssuanceBatch\EstimateIssuanceBatchPricing;
use LBHurtado\XChange\Actions\IssuanceBatch\PrepareIssuanceBatchFunding;
use LBHurtado\XChange\Contracts\Commercial\PublicIssuanceEscrowPrincipalResolverContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchImportNormalizer;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchTabularReader;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicIssuanceBatchController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, '"name","mobile","email","amount","message"'.PHP_EOL);
            fwrite($handle, '"Example Recipient","09170000000","recipient@example.com","100.00","Emergency assistance"'.PHP_EOL);
            fwrite($handle, '"Second Recipient","09171112222","second@example.com","50.00",""'.PHP_EOL);
            fclose($handle);
        }, 'pay-code-batch-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request, IssuanceBatchTabularReader $reader, IssuanceBatchImportNormalizer $normalizer, EstimateIssuanceBatchPricing $pricing): JsonResponse
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
        $validItems = collect($normalized['items'])
            ->filter(static fn (array $item): bool => $item['status'] === 'valid')
            ->map(static fn (array $item): object => (object) ['amount_minor' => $item['amount_minor']]);
        $quote = $pricing->handle($validItems);

        return response()->json([
            'sheet' => $table['sheet'],
            'headers' => $table['headers'],
            'items' => $normalized['items'],
            'total_minor' => $normalized['total_minor'],
            'pay_code_value_minor' => (int) round(($quote->pay_code_value ?? 0) * 100),
            'issuance_fee_minor' => $quote->customer_charge_minor,
            'total_due_minor' => (int) round(($quote->account_debit ?? 0) * 100),
            'currency' => $quote->currency,
            'errors' => $normalized['errors'],
            'mapping' => $mapping,
            'can_create' => $normalized['errors'] === [] && $normalized['items'] !== [],
        ]);
    }

    public function store(Request $request, PublicIssuanceEscrowPrincipalResolverContract $resolver, IssuanceBatchTabularReader $reader, IssuanceBatchImportNormalizer $normalizer, CreateIssuanceBatch $create): JsonResponse
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

        return array_filter($mapping, static fn (mixed $header, mixed $canonical): bool => is_string($canonical) && is_string($header) && in_array($header, $headers, true), ARRAY_FILTER_USE_BOTH);
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
        $items = $this->reportItems($batch);

        return response()->json([
            'reference' => $batch->reference,
            'status' => $batch->status,
            'item_count' => $batch->item_count,
            'valid_item_count' => $batch->valid_item_count,
            'total_minor' => $batch->total_minor,
            'expires_at' => $batch->expires_at?->toIso8601String(),
            'funded_at' => $batch->funded_at?->toIso8601String(),
            'issued_at' => $batch->issued_at?->toIso8601String(),
            'items' => $items->values()->all(),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function reportItems(IssuanceBatch $batch): Collection
    {
        $voucherIds = $batch->items->pluck('voucher_id')->filter()->values();
        $vouchers = Voucher::query()->whereKey($voucherIds->all())->get()->keyBy(fn (Voucher $voucher): string => (string) $voucher->getKey());
        $claims = VoucherClaim::query()->whereIn('voucher_id', $voucherIds->all())->latest('id')->get()->unique('voucher_id')->keyBy(fn (VoucherClaim $claim): string => (string) $claim->voucher_id);
        $reconciliations = DisbursementReconciliation::query()->whereIn('voucher_id', $voucherIds->all())->latest('id')->get()->unique('voucher_id')->keyBy(fn (DisbursementReconciliation $reconciliation): string => (string) $reconciliation->voucher_id);

        return $batch->items->map(function ($item) use ($vouchers, $claims, $reconciliations): array {
            $voucher = $vouchers->get((string) $item->voucher_id);
            $claim = $claims->get((string) $item->voucher_id);
            $reconciliation = $reconciliations->get((string) $item->voucher_id);
            $lifecycle = $this->batchItemLifecycle($voucher, $claim, $reconciliation, (string) $item->status);

            return [
                'ordinal' => $item->ordinal,
                'source_row' => $item->source_row,
                'name' => data_get($item->payload, 'name'),
                'mobile' => data_get($item->payload, 'mobile'),
                'email' => data_get($item->payload, 'email'),
                'amount_minor' => $item->amount_minor,
                'pay_code' => $item->pay_code,
                'claim_url' => $item->claim_url,
                'status' => $item->status,
                'lifecycle_status' => $lifecycle['status'],
                'redeemed_at' => $voucher?->redeemed_at?->toIso8601String(),
                'claim_summary' => $lifecycle['claim_summary'],
                'issued_at' => $item->issued_at?->toIso8601String(),
                'failure_message' => $item->failure_message,
            ];
        });
    }

    /** @return array{status: string, claim_summary: array<string, mixed>} */
    private function batchItemLifecycle(?Voucher $voucher, ?VoucherClaim $claim, ?DisbursementReconciliation $reconciliation, string $fallback): array
    {
        $claimStatus = strtolower((string) ($claim?->status ?? $reconciliation?->status ?? ''));
        $status = match (true) {
            in_array($claimStatus, ['pending', 'processing', 'queued'], true) => 'payout_pending',
            in_array($claimStatus, ['failed', 'rejected', 'payout_rejected'], true) => 'payout_rejected',
            in_array($claimStatus, ['paid', 'succeeded', 'withdrawn'], true) => 'paid',
            $voucher?->redeemed_at !== null || $voucher?->closed_at !== null => 'redeemed',
            $claim !== null && ($claim->remaining_balance_minor === null || (int) $claim->remaining_balance_minor <= 0) => 'redeemed',
            $claim !== null => 'partially_claimed',
            $voucher?->state?->value === 'cancelled' => 'cancelled',
            $voucher?->state?->value === 'expired' || $voucher?->isExpired() === true => 'expired',
            default => $fallback,
        };

        return [
            'status' => $status,
            'claim_summary' => $claim === null && $reconciliation === null ? [] : [
                'status' => $claimStatus !== '' ? $claimStatus : 'redeemed',
                'claimed_at' => ($reconciliation?->completed_at ?? $claim?->completed_at ?? $claim?->attempted_at ?? $claim?->created_at)?->toIso8601String(),
                'amount_minor' => $reconciliation?->amount !== null ? (int) round((float) $reconciliation->amount * 100) : ($claim?->disbursed_amount_minor ?? $claim?->requested_amount_minor),
                'currency' => $reconciliation?->currency ?? $claim?->currency ?? 'PHP',
                'claimer_name' => data_get($claim?->meta, 'inputs.name') ?? data_get($claim?->meta, 'name'),
                'claimer_mobile_masked' => $this->maskedMobile($claim?->claimer_mobile),
                'settlement_mode' => $claim?->settlement_mode,
                'settlement_rail' => $reconciliation?->settlement_rail,
                'provider' => $reconciliation?->provider,
                'bank_code' => $reconciliation?->bank_code ?? $claim->bank_code,
                'account_number_masked' => $reconciliation?->account_number_masked ?? $claim->account_number_masked,
                'provider_transaction_id' => $reconciliation?->provider_transaction_id,
                'payout_status' => $reconciliation?->status,
                'completed_at' => $reconciliation?->completed_at?->toIso8601String() ?? $claim->completed_at?->toIso8601String(),
            ],
        ];
    }

    private function maskedMobile(?string $mobile): ?string
    {
        $mobile = trim((string) $mobile);

        return $mobile === '' ? null : (strlen($mobile) <= 4 ? str_repeat('•', strlen($mobile)) : str_repeat('•', strlen($mobile) - 4).substr($mobile, -4));
    }

    public function fund(Request $request, IssuanceBatch $batch, PublicIssuanceEscrowPrincipalResolverContract $resolver, PrepareIssuanceBatchFunding $funding, CheckoutLifecycle $checkouts, PublicIssuanceOrderAccess $orders, OnDemandIssuanceFundingOrderPresenter $presenter, IssuanceBatchAccess $batches): JsonResponse
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
            fputcsv($handle, ['Row', 'Name', 'Mobile', 'Email', 'Amount', 'Pay Code', 'Status', 'Claim URL', 'Redemption Status', 'Claimed At', 'Claimer Name', 'Claimer Mobile', 'Settlement Mode', 'Settlement Rail', 'Provider', 'Bank/Wallet Code', 'Account Number', 'Redeemed Amount', 'Payout Status', 'Provider Transaction']);
            foreach ($this->reportItems($batch) as $item) {
                $claim = (array) ($item['claim_summary'] ?? []);
                fputcsv($handle, [$item['source_row'], $item['name'], $item['mobile'], $item['email'], number_format($item['amount_minor'] / 100, 2, '.', ''), $item['pay_code'], $item['status'], $item['claim_url'], $item['lifecycle_status'], $claim['claimed_at'] ?? '', $claim['claimer_name'] ?? $item['name'], $claim['claimer_mobile_masked'] ?? '', $claim['settlement_mode'] ?? '', $claim['settlement_rail'] ?? '', $claim['provider'] ?? '', $claim['bank_code'] ?? '', $claim['account_number_masked'] ?? '', isset($claim['amount_minor']) ? number_format(((int) $claim['amount_minor']) / 100, 2, '.', '') : '', $claim['payout_status'] ?? '', $claim['provider_transaction_id'] ?? '']);
            }
            fclose($handle);
        }, $batch->reference.'.csv', ['Content-Type' => 'text/csv']);
    }
}
