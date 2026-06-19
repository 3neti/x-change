<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;

class ClaimExperienceController extends Controller
{
    public function __invoke(string $code, ValidateCompiledClaimVoucher $validator): JsonResponse
    {
        $code = strtoupper(trim($code));
        $voucher = Voucher::query()->where('code', $code)->first();
        $message = $validator->handle($voucher);

        if ($message !== null) {
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $message,
                'claim_experience' => null,
            ], $voucher ? 422 : 404);
        }

        return response()->json([
            'success' => true,
            'code' => $code,
            'claim_experience' => ResolveClaimExperience::run($voucher)->toArray(),
        ]);
    }
}
