<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;

final class PaymentRecoveryReportController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        $evidence = $request->validate([
            'transaction_reference' => ['required', 'string', 'max:120'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'paid_at' => ['required', 'date'],
            'contact' => ['nullable', 'string', 'max:190'],
        ]);

        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->first();
        if ($voucher !== null) {
            DB::table('x_change_payment_recovery_reports')->insert([
                'reference' => (string) Str::ulid(),
                'voucher_id' => $voucher->getKey(),
                'evidence_ciphertext' => Crypt::encryptString(json_encode($evidence, JSON_THROW_ON_ERROR)),
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Your report has been received for review. Do not pay again.'], 202)
            ->header('Cache-Control', 'no-store, private');
    }
}
