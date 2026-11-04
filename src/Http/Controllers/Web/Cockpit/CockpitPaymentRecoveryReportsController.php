<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\VoucherAccessContract;

final class CockpitPaymentRecoveryReportsController extends Controller
{
    public function __construct(private readonly VoucherAccessContract $vouchers) {}

    public function index(Request $request, string $code): JsonResponse
    {
        $voucher = $this->authorizedVoucher($request, $code);
        $reports = DB::table('x_change_payment_recovery_reports')->where('voucher_id', $voucher->getKey())
            ->orderByDesc('id')->limit(100)->get()->map(static fn (object $report): array => [
                'reference' => $report->reference,
                'status' => $report->status,
                'evidence' => json_decode(Crypt::decryptString($report->evidence_ciphertext), true, flags: JSON_THROW_ON_ERROR),
                'created_at' => $report->created_at,
                'reviewed_at' => $report->reviewed_at,
                'reviewed_by' => $report->reviewed_by_id,
                'review_note' => $report->review_note_ciphertext === null ? null : Crypt::decryptString($report->review_note_ciphertext),
            ])->all();

        return response()->json(['reports' => $reports])->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, string $code, string $reference): JsonResponse
    {
        $voucher = $this->authorizedVoucher($request, $code);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $owner = $request->user();
        DB::transaction(function () use ($voucher, $reference, $data, $owner): void {
            $report = DB::table('x_change_payment_recovery_reports')->where('voucher_id', $voucher->getKey())
                ->where('reference', $reference)->lockForUpdate()->first();
            abort_unless($report !== null && $report->status === 'open', 404);
            DB::table('x_change_payment_recovery_reports')->where('id', $report->id)->update([
                'status' => 'reviewed', 'reviewed_at' => now(),
                'reviewed_by_type' => $owner->getMorphClass(), 'reviewed_by_id' => (string) $owner->getKey(),
                'review_note_ciphertext' => Crypt::encryptString($data['note']), 'updated_at' => now(),
            ]);
        }, attempts: 3);

        return response()->json(['status' => 'reviewed'])->header('Cache-Control', 'no-store, private');
    }

    private function authorizedVoucher(Request $request, string $code): Model
    {
        $voucher = $this->vouchers->findByCode($code);
        $owner = $request->user();
        abort_unless($voucher instanceof Model && $owner instanceof Model
            && $voucher->owner_type === $owner->getMorphClass()
            && (string) $voucher->owner_id === (string) $owner->getKey(), 404);

        return $voucher;
    }
}
