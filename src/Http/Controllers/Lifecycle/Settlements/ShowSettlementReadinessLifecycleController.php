<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle\Settlements;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Services\ApiResponseFactory;

final class ShowSettlementReadinessLifecycleController extends Controller
{
    public function __invoke(
        string $code,
        VoucherAccessContract $vouchers,
        SettlementEnvelopeReadinessContract $readiness,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $voucher = $vouchers->findByCodeOrFail($code);

        $result = $readiness->evaluate(
            voucher: $voucher,
            gate: request()->string('gate', 'settleable')->toString(),
            context: [
                'requires_envelope' => true,
                'driver' => request()->string(
                    'driver',
                    data_get($voucher->metadata, 'settlement_driver', config('x-change.settlement.default_driver', 'philhealth-bst'))
                )->toString(),
            ],
        );

        return $responses->success([
            'voucher_code' => $voucher->code,
            'required' => $result->required,
            'exists' => $result->exists,
            'ready' => $result->ready,
            'driver' => $result->driver,
            'gate' => $result->gate,
            'satisfied' => $result->satisfied,
            'missing' => $result->missing,
            'failed' => $result->failed,
            'warnings' => $result->warnings,
            'checklist' => $result->checklist,
            'payload' => $result->payload,
            'documents' => $result->documents,
            'meta' => $result->meta,
        ]);
    }
}
