<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

final class PublicIssuanceBatchPayCodeDetailController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly VoucherAccessContract $vouchers,
        private readonly IssuanceBatchAccess $batches,
    ) {}

    public function page(IssuanceBatch $batch, string $code): Response
    {
        return Inertia::render('x-change/public/BatchPayCodeDetail', [
            'batch_reference' => $batch->reference,
            'code' => $code,
            'detail_endpoint' => route(
                'x-change.public-auto-generate.batches.pay-codes.data',
                ['batch' => $batch->reference, 'code' => $code],
                false,
            ),
            'batch_pay_codes_url' => route(
                'x-change.public-auto-generate.batches.pay-codes',
                ['batch' => $batch->reference],
                false,
            ),
        ]);
    }

    public function data(Request $request, IssuanceBatch $batch, string $code): JsonResponse
    {
        $this->batches->authorizeToken($request, $batch);
        $item = $this->batches->itemForCode($batch, $code);
        $voucher = $this->vouchers->findByCode($code);

        abort_unless($voucher instanceof Voucher && (string) $item->voucher_id === (string) $voucher->getKey(), 404);

        return response()->json($this->props->toVoucherDetailArray($code));
    }
}
