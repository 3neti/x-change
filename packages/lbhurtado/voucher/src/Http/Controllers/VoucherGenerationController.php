<?php

namespace LBHurtado\Voucher\Http\Controllers;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherGenerationController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = VoucherInstructionsData::from($request->all());

        $vouchers = GenerateVouchers::run($data);

        return response()->json([
            'message' => 'Vouchers successfully generated.',
            'data' => $vouchers->map(fn (Voucher $voucher) => [
                'code' => $voucher->code,
                'metadata' => $voucher->metadata,
            ]),
        ]);
    }
}
